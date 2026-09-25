<?php

namespace Tests\Feature;

use App\Livewire\CheckoutForm;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Support\OrderWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * إتمام الطلب بلا تسجيل دخول.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * `orders.user_id` كان إلزاميًّا ومسار `/checkout` محميًّا بوسطاء الدخول،
 * فالعميل الذي لا يريد حسابًا **لا يستطيع الشراء**. وأسوأ من ذلك: نموذج الدفع
 * كان يقرأ `auth()->user()->name` بلا فحص — أي أنه **يسقط بخطأ 500** لأي زائر
 * غير مسجَّل. فالحقول صارت حقيقية وإلزامية، والطلب يُخزَّن باسم الضيف ورقمه.
 */
class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;
    private PaymentMethod $cod;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties']);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة حرير', 'name_en' => 'Silk Tie',
            'slug' => 'silk-tie', 'price_usd' => 10, 'cost_usd' => 6,
            'internal_code' => 'SILK-01',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'كحلي', 'size' => 'L', 'sku' => 'SILK-01-NVY-L', 'quantity' => 5,
        ]);

        $this->cod = PaymentMethod::create([
            'type' => 'cash_on_delivery', 'name' => 'الدفع عند الاستلام',
            'value' => '-', 'is_active' => true, 'requires_proof' => false,
        ]);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 2],
        ]);
    }

    /** @return array<string, mixed> */
    private function guestData(array $overrides = []): array
    {
        return array_merge([
            'shipping_method' => 'pickup',
            'customer_name' => 'سامر الضيف',
            'customer_phone' => '0987654365',
            'address' => 'المزة — شارع الجلاء، بناء ١٢',
            'payment_method_id' => $this->cod->id,
        ], $overrides);
    }

    // ═══════════════ الوصول ═══════════════

    public function test_a_guest_can_open_the_checkout_page(): void
    {
        $this->assertGuest();

        $this->get('/checkout')->assertOk();
    }

    /** وكان يُحوَّل إلى تسجيل الدخول — أي أن الشراء كان مشروطًا بحساب */
    public function test_the_checkout_page_does_not_force_a_login(): void
    {
        $response = $this->get('/checkout');

        $response->assertOk();
        // لا تحويل إلى الدخول: الصفحة تُعرض كاملة لمن لا حساب له.
        // (وزر «تسجيل الدخول» في الهيدر يبقى ظاهرًا — وهو خيار لا شرط.)
        $this->assertStringNotContainsString(route('login'), $response->headers->get('Location') ?? '');
    }

    // ═══════════════ إنشاء الطلب ═══════════════

    public function test_a_guest_can_place_an_order(): void
    {
        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'سامر الضيف')
            ->set('customer_phone', '0987654365')
            ->set('address', 'المزة — شارع الجلاء')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm')
            ->assertHasNoErrors();

        $order = Order::latest('id')->first();

        $this->assertNotNull($order, 'لم يُنشأ طلب');
        $this->assertNull($order->user_id, 'طلب الضيف بلا حساب');
        $this->assertSame('سامر الضيف', $order->customer_name);
        $this->assertSame('0987654365', $order->customer_phone);
        $this->assertSame('سامر الضيف', $order->customerName());
        $this->assertSame('0987654365', $order->customerPhone());
        $this->assertTrue($order->isGuest());
    }

    public function test_a_guest_does_not_get_a_user_account_created(): void
    {
        $before = User::count();

        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'ضيف')
            ->set('customer_phone', '0999888777')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm');

        $this->assertSame($before, User::count(),
            'لا يُنشأ حساب للضيف — وإلا حُجز رقم هاتفه ومنع من التسجيل لاحقًا');
    }

    /** المسجَّل يبقى طلبه مرتبطًا بحسابه، والبيانات تُقرأ من الحساب */
    public function test_a_signed_in_customer_order_still_links_to_the_account(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'name' => 'سامر المسجَّل']);

        $this->actingAs($user);

        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'سامر المسجَّل')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm');

        $order = Order::latest('id')->first();

        $this->assertSame($user->id, $order->user_id);
        $this->assertFalse($order->isGuest());
        $this->assertSame('سامر المسجَّل', $order->customerName());
    }

    // ═══════════════ الإلزام ═══════════════

    /** «نافذة إلزامية» — لا طلب بلا اسم ورقم وعنوان */
    public function test_the_customer_name_is_mandatory(): void
    {
        Livewire::test(CheckoutForm::class)
            ->set('customer_name', '')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm')
            ->assertHasErrors(['customer_name' => 'required']);

        $this->assertSame(0, Order::count());
    }

    public function test_the_phone_is_mandatory(): void
    {
        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm')
            ->assertHasErrors(['customer_phone' => 'required']);

        $this->assertSame(0, Order::count());
    }

    public function test_the_address_is_mandatory(): void
    {
        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '0987654365')
            ->set('address', '')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm')
            ->assertHasErrors(['address' => 'required']);

        $this->assertSame(0, Order::count());
    }

    // ═══════════════ صفحة النجاح ═══════════════

    public function test_a_guest_sees_the_success_page_for_the_order_they_just_placed(): void
    {
        Livewire::test(CheckoutForm::class)
            ->set('customer_name', 'سامر')
            ->set('customer_phone', '0987654365')
            ->set('address', 'دمشق')
            ->set('payment_method_id', $this->cod->id)
            ->call('confirm');

        $order = Order::latest('id')->first();

        $this->get(route('checkout.success', $order->order_code))->assertOk();
    }

    /** ومن لم يُنشئ الطلب لا يراه — ولو عرف الرمز */
    public function test_the_success_page_is_closed_to_strangers(): void
    {
        $order = Order::create([
            'order_code' => 'NDF-STRANGER',
            'status' => 'pending',
            'subtotal_usd' => 10, 'discount_usd' => 0, 'shipping_usd' => 0,
            'total_usd' => 10, 'exchange_rate' => 15000, 'total_syp' => 150000,
            'shipping_method' => 'pickup',
        ]);

        $this->get(route('checkout.success', $order->order_code))->assertNotFound();
    }

    // ═══════════════ رسالة واتساب ═══════════════

    public function test_the_whatsapp_message_carries_the_customer_and_every_product(): void
    {
        $order = Order::create([
            'customer_name' => 'سامر', 'customer_phone' => '0987654365',
            'order_code' => 'NDF-WA01', 'status' => 'pending',
            'subtotal_usd' => 30, 'discount_usd' => 0, 'shipping_usd' => 0,
            'total_usd' => 30, 'exchange_rate' => 15000, 'total_syp' => 450000,
            'shipping_method' => 'local', 'city' => 'دمشق', 'shipping_address' => 'المزة',
        ]);

        foreach ([['كرافتة حرير', 'كحلي', 'L', 2], ['طقم رسمي', 'رمادي', 'M', 1]] as [$name, $color, $size, $qty]) {
            $order->items()->create([
                'product_id' => $this->variant->product_id,
                'name_ar' => $name, 'name_en' => $name,
                'color' => $color, 'size' => $size, 'quantity' => $qty,
                'unit_price_usd' => 10, 'unit_cost_usd' => 6,
                'total_price_usd' => 10 * $qty, 'is_wholesale' => false,
            ]);
        }

        $message = OrderWhatsapp::message($order->fresh(['items.product.variants']));

        // ترويسة العميل
        $this->assertStringContainsString('مرحبًا', $message);
        $this->assertStringContainsString('سامر', $message);
        $this->assertStringContainsString('0987654365', $message);
        $this->assertStringContainsString('دمشق', $message);

        // مقطع لكل منتج — لا منتج أول ثم «وغيره»
        $this->assertStringContainsString('كرافتة حرير', $message);
        $this->assertStringContainsString('طقم رسمي', $message);

        // الحقول الخمسة المطلوبة
        foreach (['1: العدد:', '2: الألوان:', '3: الكود:', '4: التوفّر:', '5: رابط المنتج:'] as $field) {
            $this->assertStringContainsString($field, $message, "الحقل «{$field}» مفقود");
        }

        $this->assertStringContainsString('كحلي', $message);
        $this->assertStringContainsString('SILK-01-NVY-L', $message, 'رمز الصنف');
        $this->assertStringContainsString('/p/silk-tie', $message, 'رابط المنتج');
    }

    /** الرابط وحده في سطر — وإلا وصل نصًّا لا يُفتح */
    public function test_the_product_link_sits_alone_on_its_own_line(): void
    {
        $order = Order::create([
            'customer_name' => 'سامر', 'customer_phone' => '0987654365',
            'order_code' => 'NDF-WA02', 'status' => 'pending',
            'subtotal_usd' => 10, 'discount_usd' => 0, 'shipping_usd' => 0,
            'total_usd' => 10, 'exchange_rate' => 15000, 'total_syp' => 150000,
            'shipping_method' => 'pickup',
        ]);

        $order->items()->create([
            'product_id' => $this->variant->product_id,
            'name_ar' => 'كرافتة حرير', 'name_en' => 'Silk Tie',
            'color' => 'كحلي', 'size' => 'L', 'quantity' => 1,
            'unit_price_usd' => 10, 'unit_cost_usd' => 6,
            'total_price_usd' => 10, 'is_wholesale' => false,
        ]);

        $lines = explode("\n", OrderWhatsapp::message($order->fresh(['items.product.variants'])));
        $urlLine = array_values(array_filter($lines, fn ($l) => str_contains($l, '/p/silk-tie')))[0] ?? null;

        $this->assertNotNull($urlLine, 'لا رابط في الرسالة');
        $this->assertSame(trim($urlLine), $urlLine, 'الرابط وحده في السطر بلا إضافة');
        $this->assertStringStartsWith('http', trim($urlLine));
    }

    public function test_the_whatsapp_url_is_a_real_wa_me_link(): void
    {
        $order = Order::create([
            'customer_name' => 'سامر', 'customer_phone' => '0987654365',
            'order_code' => 'NDF-WA03', 'status' => 'pending',
            'subtotal_usd' => 10, 'discount_usd' => 0, 'shipping_usd' => 0,
            'total_usd' => 10, 'exchange_rate' => 15000, 'total_syp' => 150000,
            'shipping_method' => 'pickup',
        ]);

        $this->assertStringStartsWith('https://wa.me/', OrderWhatsapp::url($order));
    }
}
