<?php

namespace Tests\Feature;

use App\Livewire\AddToCart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ثلاث فجوات كانت مكشوفة تمامًا.
 *
 * ── ١. الوكيل العكسي — وهي العطل الذي كسر عرض الموقع على عميل خارج سوريا ──
 * نفق Cloudflare (وكأي موازن حِمل أو CDN) **ينهي TLS** ثم يُمرّر الطلب إلى
 * التطبيق بـ`http` ويضع الأصل في `X-Forwarded-Proto`. وبلا `trustProxies`
 * يولّد Laravel كل الأصول `http://` على صفحة `https://` — **والمتصفح يحجبها
 * (mixed content)** ⇒ الموقع يظهر بلا تنسيقات ولا صور ولا جافاسكربت.
 * عطل كامل، وبلا رسالة خطأ واحدة تدلّ عليه.
 *
 * ── ٢. إضافة إلى السلة — أخطر مسار في المتجر بلا أي اختبار ──
 * ── ٣. حدّ المخزون عند الإضافة (لا عند الشراء فقط) ──
 */
class ProxyAndCartTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة حرير', 'name_en' => 'Silk Tie',
            'slug' => 'silk-tie', 'price_usd' => 10, 'cost_usd' => 6, 'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color' => 'كحلي', 'size' => 'L', 'quantity' => 3,
        ]);
    }

    // ═══════════════ ١. الوكيل العكسي ═══════════════

    /**
     * ⚠️ الاختبار الذي كان يمنع العطل الذي كسر العرض على العميل.
     */
    public function test_assets_are_served_over_https_behind_a_proxy(): void
    {
        $html = $this->get('/p/'.$this->product->slug, ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->getContent();

        $insecure = preg_match_all('/="http:\/\/[^"]+\.(css|js|png|jpg|webp|svg)"/', $html);

        $this->assertSame(0, $insecure,
            'أصول http على صفحة https ⇒ المتصفح يحجبها ويظهر الموقع مهدومًا');

        $this->assertStringContainsString('https://', $html, 'الأصول تُبنى https');
    }

    /** وطلب مباشر بلا وكيل يبقى على البروتوكول الذي وصل به */
    public function test_without_a_proxy_the_scheme_follows_the_request(): void
    {
        $html = $this->get('/p/'.$this->product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('http://localhost', $html);
    }

    public function test_the_forwarded_host_is_used_in_generated_urls(): void
    {
        $html = $this->get('/p/'.$this->product->slug, [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'nadaf.store',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('nadaf.store', $html, 'المضيف المُمرَّر يُحترم');
    }

    // ═══════════════ ٢. إضافة إلى السلة ═══════════════

    public function test_adding_a_product_puts_it_in_the_cart(): void
    {
        Livewire::test(AddToCart::class, ['product' => $this->product])
            ->set('color', 'كحلي')
            ->set('size', 'L')
            ->set('qty', 2)
            ->call('add')
            ->assertHasNoErrors();

        $this->assertSame(2, CartService::count(), 'القطعتان في السلة');
    }

    public function test_adding_twice_accumulates_the_quantity(): void
    {
        Livewire::test(AddToCart::class, ['product' => $this->product])
            ->set('color', 'كحلي')->set('size', 'L')->set('qty', 1)
            ->call('add')
            ->call('add');

        $this->assertSame(2, CartService::count());
    }

    /** ⚠️ الحدّ يُفحص عند **الإضافة** لا عند الشراء فقط — وإلا امتلأت السلة بما لا يكفي */
    public function test_you_cannot_add_more_than_the_available_stock(): void
    {
        Livewire::test(AddToCart::class, ['product' => $this->product])
            ->set('color', 'كحلي')->set('size', 'L')
            ->set('qty', 99)
            ->call('add');

        $this->assertLessThanOrEqual(3, CartService::count(),
            'لا تُقبل كمية أكبر من المخزون');
    }

    public function test_an_out_of_stock_variant_is_refused(): void
    {
        $this->variant->update(['quantity' => 0]);

        Livewire::test(AddToCart::class, ['product' => $this->product->fresh('variants')])
            ->set('color', 'كحلي')->set('size', 'L')->set('qty', 1)
            ->call('add');

        $this->assertSame(0, CartService::count());
    }

    /** المنتج بخيارات متعددة يُفتح على متغيّر **متوفّر** لا على «غير متوفر» */
    public function test_the_default_variant_is_an_in_stock_one(): void
    {
        ProductVariant::create([
            'product_id' => $this->product->id,
            'color' => 'نبيتي', 'size' => 'M', 'quantity' => 0,
        ]);

        $component = Livewire::test(AddToCart::class, ['product' => $this->product->fresh('variants')]);

        $this->assertGreaterThan(0, $component->get('maxQty'),
            'يُفتح على متغيّر متوفّر — وإلا ظنّ الزائر أن المنتج نافد');
    }

    // ═══════════════ ٣. السلة والصفحات ═══════════════

    public function test_the_cart_page_reflects_the_session(): void
    {
        Session::put(CartService::SESSION_KEY, [
            'v'.$this->variant->id => ['key' => 'v'.$this->variant->id, 'qty' => 2],
        ]);

        $this->get('/cart')->assertOk()->assertSee('كرافتة حرير', escape: false);
    }

    /** البحث يُرقَّم — وإلا غرق المتجر في صفحة واحدة طويلة */
    public function test_the_search_page_paginates(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'category_id' => $this->product->category_id,
                'name_ar' => 'كرافتة رقم '.$i, 'name_en' => 'Tie '.$i,
                'slug' => 'tie-'.$i, 'price_usd' => 10, 'is_active' => true,
            ]);
        }

        $html = $this->get('/search?q=كرافتة')->assertOk()->getContent();

        // ١٥ نتيجة ⇒ أكثر من صفحة واحدة
        $this->assertMatchesRegularExpression('/page=2|الصفحة|pagination|paginat/i', $html,
            'نتائج البحث مُرقَّمة');
    }
}
