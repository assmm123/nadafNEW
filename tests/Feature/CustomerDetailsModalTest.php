<?php

namespace Tests\Feature;

use App\Livewire\CustomerDetailsModal;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Support\OrderWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * النافذة الإلزامية لبيانات العميل.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * «إتمام الطلب» و«الشراء الآن» و«إرسال على واتساب» تحتاج كلها اسم العميل
 * ورقمه وعنوانه. وكانت سلة المشتريات **تُحوّل الزائر غير المسجَّل إلى
 * `/login`**، ونافذة الدفع تفعل مثله — فيُشترط حساب على من لا يريد حسابًا.
 *
 * فالنافذة هنا مرة واحدة: تجمع البيانات، تحفظها في الجلسة، ثم تُرسل الرسالة.
 * والصيغة المطلوبة في `OrderWhatsapp` — مصدر واحد لا ثلاث نسخ.
 */
class CustomerDetailsModalTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name_ar' => 'أطقم', 'name_en' => 'Sets', 'slug' => 'sets']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'طقم هدايا رسمي', 'name_en' => 'Formal Gift Set',
            'slug' => 'formal-gift-set', 'price_usd' => 50, 'cost_usd' => 30,
            'internal_code' => 'GIFT-01',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color' => 'كحلي', 'size' => 'L', 'sku' => 'GIFT-01-NVY', 'quantity' => 4,
        ]);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 2],
        ]);
    }

    private function modal(string $target = 'cart', ?int $productId = null, ?int $variantId = null, int $qty = 1)
    {
        return Livewire::test(CustomerDetailsModal::class)
            ->call('openModal', $target, $productId, $variantId, $qty);
    }

    // ═══════════════ الفتح ═══════════════

    public function test_the_modal_opens_from_a_cart_request(): void
    {
        $this->modal('cart')->assertSet('open', true)->assertSet('target', 'cart');
    }

    public function test_the_modal_opens_for_a_single_product(): void
    {
        $this->modal('product', $this->product->id, $this->variant->id, 3)
            ->assertSet('open', true)
            ->assertSet('target', 'product')
            ->assertSet('quantity', 3);
    }

    /** ولا تسجيل دخول مطلوبًا */
    public function test_a_guest_can_use_the_modal(): void
    {
        $this->assertGuest();
        $this->modal('cart')->assertOk();
    }

    // ═══════════════ الإلزام ═══════════════

    public function test_the_name_is_mandatory(): void
    {
        $this->modal('cart')
            ->set('name', '')
            ->set('phone', '0987654365')
            ->set('address', 'دمشق')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->assertSet('open', true);
    }

    public function test_the_phone_is_mandatory(): void
    {
        $this->modal('cart')
            ->set('name', 'سامر')
            ->set('phone', '')
            ->set('address', 'دمشق')
            ->call('save')
            ->assertHasErrors(['phone' => 'required']);
    }

    public function test_the_address_is_mandatory(): void
    {
        $this->modal('cart')
            ->set('name', 'سامر')
            ->set('phone', '0987654365')
            ->set('address', '')
            ->call('save')
            ->assertHasErrors(['address' => 'required']);
    }

    // ═══════════════ الرسالة ═══════════════

    public function test_saving_sends_the_cart_to_whatsapp(): void
    {
        $this->modal('cart')
            ->set('name', 'سامر')
            ->set('phone', '0987654365')
            ->set('address', 'المزة')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectContains('wa.me');
    }

    public function test_saving_sends_a_single_product_to_whatsapp(): void
    {
        $this->modal('product', $this->product->id, $this->variant->id, 3)
            ->set('name', 'سامر')
            ->set('phone', '0987654365')
            ->set('address', 'المزة')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectContains('wa.me');
    }

    /** البيانات تُحفظ في الجلسة فلا تُكتب مرتين */
    public function test_the_details_are_kept_in_the_session(): void
    {
        $this->modal('cart')
            ->set('name', 'سامر')
            ->set('phone', '0987654365')
            ->set('address', 'المزة')
            ->call('save');

        $this->assertSame('سامر', session('customer_details.name'));
        $this->assertSame('0987654365', session('customer_details.phone'));
    }

    // ═══════════════ صيغة الرسالة ═══════════════

    public function test_the_message_follows_the_required_format(): void
    {
        $message = OrderWhatsapp::forProduct(
            $this->product,
            $this->variant,
            3,
            ['name' => 'سامر', 'phone' => '0987654365', 'address' => 'دمشق — المزة'],
        );

        $this->assertStringContainsString('مرحبًا', $message);
        $this->assertStringContainsString('الاسم: سامر + الرقم: 0987654365 + العنوان: دمشق — المزة', $message);
        $this->assertStringContainsString('أستفسر عن هذا المنتج:', $message);
        $this->assertStringContainsString('الاسم: طقم هدايا رسمي', $message);
        $this->assertStringContainsString('1: العدد: 3', $message);
        $this->assertStringContainsString('2: الألوان: كحلي', $message);
        $this->assertStringContainsString('3: الكود: GIFT-01-NVY', $message);
        $this->assertStringContainsString('4: التوفّر: متوفّر', $message);
        $this->assertStringContainsString('5: رابط المنتج:', $message);
        $this->assertStringContainsString('/p/formal-gift-set', $message);
    }

    /**
     * ⚠️ جوهر المتطلب: «إذا كان هناك عدة منتجات، يظهر كل منتج بتفاصيله في
     * مقطع منفصل» — لا منتج أول ثم «وغيره».
     */
    public function test_the_cart_message_has_a_separate_block_per_product(): void
    {
        $second = Product::create([
            'category_id' => $this->product->category_id,
            'name_ar' => 'كرافتة حرير', 'name_en' => 'Silk Tie',
            'slug' => 'silk-tie', 'price_usd' => 10, 'internal_code' => 'SILK-02',
        ]);
        ProductVariant::create(['product_id' => $second->id, 'color' => 'رمادي', 'quantity' => 9]);

        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 2],
            'v'.$second->variants()->first()->id => ['key' => 'v'.$second->variants()->first()->id, 'qty' => 1],
        ]);

        $message = OrderWhatsapp::forCart(
            CartService::totals()['items'],
            ['name' => 'سامر', 'phone' => '0987654365', 'address' => 'دمشق'],
        );

        $this->assertSame(2, substr_count($message, 'أستفسر عن هذا المنتج:'),
            'مقطع لكل منتج — لا مقطع واحد يجمعها');
        $this->assertSame(2, substr_count($message, '5: رابط المنتج:'));
        $this->assertStringContainsString('طقم هدايا رسمي', $message);
        $this->assertStringContainsString('كرافتة حرير', $message);
        $this->assertStringContainsString('/p/formal-gift-set', $message);
        $this->assertStringContainsString('/p/silk-tie', $message);
    }

    /** الرابط وحده في سطر — وإلا وصل نصًّا لا يُفتح */
    public function test_every_product_link_sits_alone_on_its_line(): void
    {
        $message = OrderWhatsapp::forProduct(
            $this->product, $this->variant, 1,
            ['name' => 'سامر', 'phone' => '0987654365', 'address' => 'دمشق'],
        );

        foreach (explode("\n", $message) as $line) {
            if (str_contains($line, '/p/formal-gift-set')) {
                $this->assertSame(trim($line), $line);
                $this->assertStringStartsWith('http', trim($line));

                return;
            }
        }

        $this->fail('لا رابط منتج في الرسالة');
    }
}
