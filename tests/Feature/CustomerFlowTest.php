<?php

namespace Tests\Feature;

use App\Livewire\AddToCart;
use App\Livewire\CustomerDetailsModal;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ═══ التدفق ═══
 *
 * الاختبارات الوظيفية تفحص **القطع**: هذا المكوّن يعمل، وهذه الخدمة تعمل.
 * وهذه تفحص **الرحلة كاملة**: من فتح المنتج إلى وصول الطلب للمالك.
 *
 * والفرق جوهري: كل قطعة قد تعمل وحدها وتنكسر عند وصلها — سلة لا تُفرَّغ،
 * مخزون يُخصم مرتين، أو طلب يُنشأ ولا يصل إشعاره.
 */
class CustomerFlowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private ProductVariant $variant;
    private PaymentMethod $cod;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة حرير', 'name_en' => 'Silk Tie',
            'slug' => 'silk-tie', 'price_usd' => 25, 'cost_usd' => 15,
            'internal_code' => 'SILK-01', 'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color' => 'كحلي', 'size' => 'L', 'sku' => 'SILK-01-NVY',
            'quantity' => 10, 'low_stock_threshold' => 3,
        ]);

        $this->cod = PaymentMethod::create([
            'type' => 'cash_on_delivery', 'name' => 'الدفع عند الاستلام',
            'value' => '-', 'is_active' => true, 'requires_proof' => false,
        ]);
    }

    // ═══════════════ ١. رحلة الضيف كاملة ═══════════════

    public function test_a_guest_completes_the_whole_journey(): void
    {
        $this->assertGuest();

        // ١) يفتح المنتج
        $this->get('/p/'.$this->product->slug)->assertOk();

        // ٢) يضيفه للسلة
        Livewire::test(AddToCart::class, ['product' => $this->product])
            ->set('color', 'كحلي')->set('size', 'L')->set('qty', 2)
            ->call('add')
            ->assertHasNoErrors();

        $this->assertSame(2, CartService::count(), 'لم تُضف القطعة');

        // ٣) يرى السلة
        $this->get('/cart')->assertOk()->assertSee('كرافتة حرير', escape: false);

        // ٤) يصل الدفع بلا حساب
        $this->get('/checkout')->assertOk();

        // ٥) يُتمّ الطلب
        Livewire::test(\App\Livewire\CheckoutForm::class)
            ->set('customer_name', 'سامر الضيف')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق — المزة')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm')
            ->assertHasNoErrors();

        // ٦) الطلب موجود بالبيانات الصحيحة
        $order = Order::latest('id')->first();

        $this->assertNotNull($order, 'لم يُنشأ الطلب');
        $this->assertNull($order->user_id, 'طُلب حساب من ضيف');
        $this->assertSame('سامر الضيف', $order->customerName());
        $this->assertSame(50.0, (float) $order->subtotal_usd);
        // قطعتان من منتج واحد = **بند واحد** بكمية ٢ لا بندان
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(2, (int) $order->items()->first()->quantity);

        // ٧) المخزون خُصم مرة واحدة
        $this->assertSame(8, (int) $this->variant->fresh()->quantity);

        // ٨) السلة فُرّغت
        $this->assertSame(0, CartService::count(), 'السلة لم تُفرَّغ بعد الطلب');

        // ٩) يرى صفحة النجاح
        $this->get(route('checkout.success', $order->order_code))->assertOk();

        // ١٠) ورسالة واتساب تحمل كل شيء
        $message = \App\Support\OrderWhatsapp::message($order->fresh(['items.product.variants']));

        $this->assertStringContainsString('سامر الضيف', $message);
        $this->assertStringContainsString('0987654365', $message);
        $this->assertStringContainsString('كرافتة حرير', $message);
        $this->assertStringContainsString('/p/silk-tie', $message);
    }

    // ═══════════════ ٢. رحلة المسجَّل ═══════════════

    public function test_a_registered_customer_journey_links_the_order_to_the_account(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'name' => 'سامر']);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 1],
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Livewire\CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm');

        $order = Order::latest('id')->first();

        $this->assertSame($user->id, $order->user_id);

        // ويراه في حسابه
        $this->get(route('account.order', $order->order_code))->assertOk();
        $this->get(route('account.orders'))->assertOk();
    }

    // ═══════════════ ٣. الطلب عبر واتساب بلا حساب ═══════════════

    public function test_the_whatsapp_route_works_without_an_account(): void
    {
        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 1],
        ]);

        Livewire::test(CustomerDetailsModal::class)
            ->call('openModal', 'product', $this->product->id, $this->variant->id, 3)
            ->set('name', 'سامر')
            ->set('phone', '0987654365')
            ->set('address', 'دمشق')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectContains('wa.me');

        // ولم يُنشأ طلب ولا حساب — الطلب عبر واتساب لا يمرّ بالدفع
        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());
        $this->assertSame(10, (int) $this->variant->fresh()->quantity, 'خُصم مخزون بلا طلب');
    }

    // ═══════════════ ٤. الطلب المختلط (منتجان) ═══════════════

    public function test_an_order_with_two_products_carries_both(): void
    {
        $second = Product::create([
            'category_id' => $this->product->category_id,
            'name_ar' => 'طقم رسمي', 'name_en' => 'Formal Set',
            'slug' => 'formal-set', 'price_usd' => 75, 'internal_code' => 'SET-02', 'is_active' => true,
        ]);
        $v2 = ProductVariant::create([
            'product_id' => $second->id, 'color' => 'رمادي', 'size' => 'M', 'quantity' => 5,
        ]);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 2],
            'v'.$v2->id => ['key' => 'v'.$v2->id, 'qty' => 1],
        ]);

        Livewire::test(\App\Livewire\CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm');

        $order = Order::latest('id')->first()->fresh(['items.product.variants']);

        $this->assertSame(2, $order->items->count());
        $this->assertSame(125.0, (float) $order->subtotal_usd, '25×2 + 75×1');

        // ومقطع منفصل لكل منتج في الرسالة
        $message = \App\Support\OrderWhatsapp::message($order);
        $this->assertSame(2, substr_count($message, 'أستفسر عن هذا المنتج:'));
        $this->assertSame(2, substr_count($message, '5: رابط المنتج:'));
    }

    // ═══════════════ ٥. أثر الطلب على المخزون والمالك ═══════════════

    public function test_the_order_reaches_the_admin_panel(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 1],
        ]);

        Livewire::test(\App\Livewire\CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm');

        $order = Order::latest('id')->first();

        // يظهر في اللوحة
        $this->actingAs($owner)->get('/admin')->assertOk();

        // وله فاتورة تُفتح
        $this->actingAs($owner)->get(route('admin.invoice', $order))->assertOk();
    }

    public function test_a_low_stock_order_does_not_break_the_journey(): void
    {
        $this->variant->update(['quantity' => 1]);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 1],
        ]);

        Livewire::test(\App\Livewire\CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame(0, (int) $this->variant->fresh()->quantity);

        // والطلب التالي على نفس القطعة يُرفض ولا يخصم
        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 1],
        ]);

        $before = Order::count();

        try {
            Livewire::test(\App\Livewire\CheckoutForm::class)
                ->set('customer_name', 'سامر')
                ->set('customer_phone', '0987654365')
                ->set('address', 'دمشق')
                ->set('payment_method_id', $this->cod->id)
                ->call('confirm');
        } catch (\Throwable) {
        }

        $this->assertSame($before, Order::count(), 'بيع قطعة غير موجودة');
        $this->assertSame(0, (int) $this->variant->fresh()->quantity, 'المخزون صار سالبًا');
    }
}
