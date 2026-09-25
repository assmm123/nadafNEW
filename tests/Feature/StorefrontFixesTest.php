<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Support\OrderWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * الأعطال الأربعة التي رصدها المالك في الواجهة.
 *
 * ١. صورة المنتج لا تُفتح عند النقر عليها.
 * ٢. «الأقسام» لا يُفتح — والسبب كان `overflow-x:auto` على شريط التنقّل
 *    **يقصّ** القائمة المنسدلة المطلقة. (وقد تُحقّق من غياب `overflow` أدناه.)
 * ٣. «من نحن» تظهر بتنسيقات بيضاء قديمة — لأنها كانت تستخدم `layouts.app`
 *    القديم، ولأن `product-card` القديم كان لا يزال يخدم صفحة البحث.
 * ٤. زر واتساب بجانب «الشراء الآن» — ويُظهر كل منتج في مقطع منفصل.
 */
class StorefrontFixesTest extends TestCase
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
            'internal_code' => 'GIFT-01', 'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color' => 'كحلي', 'size' => 'L', 'sku' => 'GIFT-01-NVY', 'quantity' => 5,
        ]);

        // صورة للمنتج — بلا وسائط يُرسم بديل فارغ ولا يُختبر العارض
        \App\Models\ProductMedia::create([
            'product_id' => $this->product->id,
            'type' => 'image',
            'file_path' => 'products/gift.jpg',
            'is_main' => true,
            'sort_order' => 0,
        ]);
    }

    // ═══════════════ ① عارض الصورة ═══════════════

    public function test_the_product_image_opens_a_full_view_on_click(): void
    {
        $html = $this->get('/p/'.$this->product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('cursor-zoom-in', $html, 'الصورة قابلة للنقر');
        $this->assertStringContainsString('role="dialog"', $html, 'عارض الصورة موجود');
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('x-show="zoom !== null"', $html);
        $this->assertStringContainsString('@keydown.escape.window="zoom = null"', $html, 'يُغلق بمفتاح Escape');
    }

    // ═══════════════ ② شريط التنقّل ═══════════════

    public function test_the_navigation_strip_is_present_on_the_storefront(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('nad-navrow', $html, 'شريط التنقّل موجود');
        $this->assertStringContainsString(__('nav.categories'), $html, 'وفيه «الأقسام»');
    }

    /**
     * ⚠️ جوهر العطل: `overflow-x:auto` على الشريط يقصّ القائمة المنسدلة
     * المطلقة فيُفتح «الأقسام» ولا يُرى شيء. فهذا الاختبار يمنع عودته.
     */
    public function test_the_navigation_row_does_not_clip_its_dropdown(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $start = strpos($css, '.nad-navrow{');
        $this->assertNotFalse($start, 'لم أجد صنف الشريط');

        $rule = substr($css, $start, strpos($css, '}', $start) - $start);

        $this->assertStringNotContainsString('overflow', $rule,
            'أي overflow على الشريط يقصّ القائمة المنسدلة فيختفي «الأقسام»');
        $this->assertStringContainsString('flex-wrap', $rule, 'فيلتفّ عند الضيق بدل أن يمرّ أفقيًّا');
    }

    // ═══════════════ ③ التنسيقات القديمة ═══════════════

    /** الصفحات الثماني صارت على هوية نداف — لا تخطيط أبيض */
    public function test_no_page_uses_the_legacy_white_layout(): void
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        $offenders = [];
        foreach ($files as $file) {
            if (str_contains(file_get_contents($file), "@extends('layouts.app')")) {
                $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $offenders,
            "صفحات تستخدم التخطيط الأبيض القديم:\n- ".implode("\n- ", $offenders));
    }

    public function test_the_about_page_renders_with_the_nad_identity(): void
    {
        \App\Models\Page::create([
            'slug' => 'about', 'title_ar' => 'من نحن', 'title_en' => 'About',
            'content_ar' => 'نص', 'content_en' => 'text', 'is_active' => true,
        ]);

        $html = $this->get('/page/about')->assertOk()->getContent();

        $this->assertStringContainsString('nad-navrow', $html, 'هيدر نداف');
        $this->assertStringNotContainsString('bg-white text-navy-900', $html, 'ولا جسم أبيض');
    }

    /** كرت المنتج القديم أُزيل — وصفحة البحث تستخدم كرت نداف */
    public function test_the_search_page_uses_the_nad_product_card(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/partials/product-card.blade.php'),
            'الكرت القديم ما زال في شجرة العرض');

        $html = $this->get('/search?q=طقم')->assertOk()->getContent();
        $this->assertStringContainsString('nad-pcard', $html, 'كرت نداف في نتائج البحث');
    }

    // ═══════════════ ④ زر واتساب بجانب الشراء الآن ═══════════════

    public function test_the_product_page_offers_whatsapp_beside_buy_now(): void
    {
        $html = $this->get('/p/'.$this->product->slug)->assertOk()->getContent();

        $this->assertStringContainsString(__('product.buy_now'), $html);
        $this->assertStringContainsString(__('checkout.confirm_with_whatsapp'), $html,
            'زر «إتمام الطلب + إرسال واتساب»');
        $this->assertStringContainsString("open-customer-details", $html, 'يفتح النافذة الإلزامية');
    }

    public function test_the_cart_offers_checkout_and_whatsapp_to_guests(): void
    {
        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 1],
        ]);

        $html = $this->get('/cart')->assertOk()->getContent();

        // كان الزائر يُحوَّل إلى `/login` — والآن يُتمّ طلبه بلا حساب
        $this->assertStringContainsString(route('checkout'), $html, 'رابط إتمام الطلب');
        $this->assertStringContainsString(__('checkout.confirm_with_whatsapp'), $html, 'وزر واتساب');

        // ولا رابط `login_to_checkout` — كان نصّ الزر للزائر يُحوّله إلى الدخول
        $this->assertStringNotContainsString(__('cart.login_to_checkout'), $html,
            'السلة لا تُحوّل الزائر إلى تسجيل الدخول');
    }

    // ═══════════════ عطل ظهر أثناء الفحص: «غير متوفر» كذبًا ═══════════════

    /**
     * منتج بخيارات متعددة ولا اختيار → كان `variant` لا يطابق شيئًا فيصير
     * المخزون صفرًا وتُعرض «غير متوفر حاليًا» وهو يملك مخزونًا. فيظنّ الزائر
     * أنه نافد ويمضي.
     */
    public function test_a_product_with_stock_is_not_shown_as_out_of_stock(): void
    {
        $html = $this->get('/p/'.$this->product->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString(__('product.out_of_stock'), $html,
            'المنتج يملك مخزونًا فلا يُعرض نافدًا');
        $this->assertStringContainsString(__('product.stock_left', ['count' => 5]), $html);
    }

    public function test_the_whatsapp_message_lists_every_product_separately(): void
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

        $this->assertSame(2, substr_count($message, 'أستفسر عن هذا المنتج:'));
        $this->assertSame(2, substr_count($message, '5: رابط المنتج:'));
    }
}
