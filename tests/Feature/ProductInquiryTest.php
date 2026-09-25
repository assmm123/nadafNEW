<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\ProductInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * رسالة الاستفسار عن منتج.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * كان زر الاستفسار يفتح المحادثة **فارغة**: `CommunicationMethod::link()`
 * تُنتج رابط قناة مجرّدًا بلا نصّ، والمسار الاحتياطي وحده كان يحمل **اسم
 * المنتج فقط**. فيصل المالك إلى محادثة بلا بيان ويسأل «أي منتج؟».
 *
 * وأخطر قاعدة هنا: **السعر لا يُدرج حين يكون المتجر يخفيه عمدًا** — وإلا
 * صارت الرسالة تسريبًا لما نُخفيه، ونقضت الغرض من وضع «السعر عند التواصل».
 */
class ProductInquiryTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = [], array $variants = []): Product
    {
        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties-'.uniqid()]);

        $product = Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'كرافات كلاسيك حرير',
            'name_en' => 'Classic Silk Tie',
            'slug' => 'classic-silk-'.uniqid(),
            'price_usd' => 25,
            'cost_usd' => 15,
            'internal_code' => 'CLA-01',
        ], $attributes));

        foreach ($variants as $variant) {
            ProductVariant::create(array_merge(['product_id' => $product->id, 'quantity' => 5], $variant));
        }

        return $product->fresh();
    }

    // ═══════════════ المحتوى ═══════════════

    public function test_the_message_carries_the_full_product_details(): void
    {
        $product = $this->product();
        $message = ProductInquiry::message($product);

        $this->assertStringContainsString('كرافات كلاسيك حرير', $message);
        $this->assertStringContainsString('Classic Silk Tie', $message, 'الاسم الإنجليزي أيضًا');
        $this->assertStringContainsString('CLA-01', $message, 'الرمز الداخلي');
        $this->assertStringContainsString('متوفّر', $message);
    }

    /** الرابط المطلق هو ما يجعل الرسالة مفيدة من أي تطبيق خارجي */
    public function test_the_message_carries_an_absolute_link_to_the_product(): void
    {
        $product = $this->product();
        $message = ProductInquiry::message($product);

        $url = ProductInquiry::url($product);

        $this->assertStringStartsWith('http', $url, 'رابط مطلق لا نسبي');
        $this->assertStringContainsString($url, $message);
        $this->assertStringContainsString($product->slug, $message);
    }

    public function test_the_message_lists_available_specs_when_no_variant_is_chosen(): void
    {
        $product = $this->product([], [
            ['color' => 'كحلي', 'size' => 'L'],
            ['color' => 'نبيتي', 'size' => 'M'],
        ]);

        $message = ProductInquiry::message($product);

        $this->assertStringContainsString('الألوان', $message);
        $this->assertStringContainsString('كحلي', $message);
        $this->assertStringContainsString('المقاسات', $message);
        $this->assertStringContainsString('L', $message);
    }

    public function test_a_chosen_variant_is_stated_instead_of_the_whole_list(): void
    {
        $product = $this->product([], [['color' => 'كحلي', 'size' => 'L', 'sku' => 'CLA-01-NVY-L']]);

        $message = ProductInquiry::message($product, $product->variants->first());

        $this->assertStringContainsString('المواصفة', $message);
        $this->assertStringContainsString('كحلي', $message);
        $this->assertStringContainsString('CLA-01-NVY-L', $message);
        $this->assertStringNotContainsString('الألوان المتاحة', $message);
    }

    public function test_an_out_of_stock_product_says_so(): void
    {
        $product = $this->product([], [['quantity' => 0]]);

        $this->assertStringContainsString('غير متوفّر', ProductInquiry::message($product));
    }

    // ═══════════════ السعر — القاعدة الأخطر ═══════════════

    public function test_the_price_is_included_when_the_store_shows_it(): void
    {
        $product = $this->product();

        $this->assertTrue(ProductInquiry::showsPrice($product));
        $this->assertStringContainsString('السعر', ProductInquiry::message($product));
    }

    /**
     * ⚠️ القاعدة التي لا تُخالف: وضع «السعر عند التواصل» يخفي السعر عمدًا.
     * وإدراجه في الرسالة يسرّب ما نُخفيه — فيصير الزر نقضًا للغرض منه.
     */
    public function test_the_price_is_withheld_in_inquiry_mode(): void
    {
        Setting::set('hide_prices', '1');
        $product = $this->product();

        $message = ProductInquiry::message($product);

        $this->assertFalse(ProductInquiry::showsPrice($product));
        $this->assertStringNotContainsString('السعر', $message);
        $this->assertStringNotContainsString('25', $message, 'ولا رقم السعر نفسه');
    }

    public function test_the_price_is_withheld_when_the_owner_hid_it_for_one_product(): void
    {
        $product = $this->product(['hide_price' => true]);

        $this->assertFalse(ProductInquiry::showsPrice($product));
        $this->assertStringNotContainsString('السعر', ProductInquiry::message($product));
    }

    public function test_the_price_is_withheld_when_the_owner_hid_the_unit_price(): void
    {
        $product = $this->product(['hide_unit_price' => true]);

        $this->assertFalse(ProductInquiry::showsPrice($product));
        $this->assertStringNotContainsString('السعر', ProductInquiry::message($product));
    }

    // ═══════════════ الرابط ═══════════════

    public function test_the_whatsapp_url_carries_the_encoded_message(): void
    {
        Setting::set('whatsapp_number', '00963930322406');

        $product = $this->product();
        $url = ProductInquiry::whatsappUrl($product);

        $this->assertStringStartsWith('https://wa.me/963930322406?text=', $url);
        $this->assertStringContainsString(rawurlencode($product->name_ar), $url);
    }

    public function test_whatsapp_availability_is_reported_honestly(): void
    {
        $this->assertFalse(ProductInquiry::hasWhatsapp(), 'بلا رقم لا زر');

        Setting::set('whatsapp_number', '0930322406');
        $this->assertTrue(ProductInquiry::hasWhatsapp());
    }

    // ═══════════════ التكامل مع الصفحة ═══════════════

    public function test_the_product_page_links_to_whatsapp_with_the_product_details(): void
    {
        Setting::set('whatsapp_number', '00963930322406');
        Setting::set('inquiry_enabled_global', '1');

        $product = $this->product();

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('wa.me/963930322406', $html, 'رابط واتساب ببيانات المنتج');
        $this->assertStringContainsString(rawurlencode($product->name_ar), $html, 'والرسالة تحمل اسم المنتج');
        $this->assertStringContainsString($product->slug, $html, 'ورابط المنتج');
    }
}
