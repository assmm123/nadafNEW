<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * مرور شامل على **كل** مسارات المتجر العامة والخاصة.
 *
 * ── لماذا هذا الملف ──
 * كانت الاختبارات تغطي لوحة الأدمن صفحةً صفحة، والمتجر جزئيًّا. فبقيت مسارات
 * كاملة بلا أي فحص: صفحة «من نحن»، البحث، السلة، الحساب، فاتورة العميل،
 * تبديل اللغة والعملة. وأي عطل فيها لا يظهر إلا بمرور زائر حقيقي.
 *
 * وهذا الملف لا يفحص **محتوى** الصفحات — يفحص أنها **تُبنى بلا سقوط**.
 * فحص المحتوى مكانه اختبارات الأقسام.
 */
class StorefrontSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Category $category;
    private User $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties', 'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name_ar' => 'كرافتة حرير',
            'name_en' => 'Silk Tie',
            'slug' => 'silk-tie',
            'price_usd' => 10,
            'cost_usd' => 6,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $this->product->id,
            'color' => 'كحلي', 'size' => 'L', 'quantity' => 5,
        ]);

        $this->customer = User::factory()->create(['role' => 'customer']);

        $this->order = Order::create([
            'user_id' => $this->customer->id,
            'order_code' => 'NDF-TEST01',
            'status' => 'pending',
            'subtotal_usd' => 10,
            'discount_usd' => 0,
            'shipping_usd' => 0,
            'total_usd' => 10,
            'exchange_rate' => 15000,
            'total_syp' => 150000,
            'shipping_method' => 'pickup',
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function publicPaths(): array
    {
        return [
            'الرئيسية' => ['/'],
            'الأقسام' => ['/c/ties'],
            'المنتج' => ['/p/silk-tie'],
            'البحث' => ['/search?q=حرير'],
            'البحث فارغًا' => ['/search'],
            'تواصل معنا' => ['/contact'],
            'السلة' => ['/cart'],
            'تبديل اللغة' => ['/lang/en'],
            'تبديل العملة' => ['/currency/syp'],
            'صحة التطبيق' => ['/up'],
        ];
    }

    #[DataProvider('publicPaths')]
    public function test_a_public_page_renders_without_crashing(string $path): void
    {
        $response = $this->get($path);

        $this->assertLessThan(500, $response->getStatusCode(), "المسار {$path} يسقط");
    }

    /** @return array<string, array{0: string}> */
    public static function guestAuthPaths(): array
    {
        return [
            'الدخول' => ['/login'],
            'التسجيل' => ['/register'],
            'نسيت كلمة المرور' => ['/forgot-password'],
        ];
    }

    #[DataProvider('guestAuthPaths')]
    public function test_a_guest_auth_page_renders(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_the_password_reset_page_renders_with_a_real_token(): void
    {
        $token = Password::createToken($this->customer);

        $this->get('/reset-password/'.$token)->assertOk();
    }

    public function test_the_account_pages_render_for_a_signed_in_customer(): void
    {
        $this->actingAs($this->customer);

        $this->get('/account')->assertOk();
        $this->get('/account/orders')->assertOk();
        $this->get('/account/orders/NDF-TEST01')->assertOk();
        $this->get('/account/orders/NDF-TEST01/invoice')->assertOk();
        $this->get('/checkout')->assertOk();
    }

    public function test_the_order_success_page_renders(): void
    {
        $this->actingAs($this->customer)
            ->get('/checkout/success/NDF-TEST01')
            ->assertOk();
    }

    /**
     * صفحة CMS من إعدادات الموقع — مسارها لم يكن مفحوصًا إطلاقًا.
     */
    public function test_a_custom_page_renders(): void
    {
        $page = Page::create([
            'slug' => 'about-us',
            'title_ar' => 'من نحن',
            'title_en' => 'About us',
            'content_ar' => 'نصّ الصفحة',
            'content_en' => 'Page body',
            'is_active' => true,
        ]);

        $this->get('/page/'.$page->slug)->assertOk();
    }

    public function test_the_language_switch_actually_changes_the_locale(): void
    {
        $this->get('/lang/en')->assertRedirect();
        $this->assertSame('en', session('locale'));
    }

    public function test_the_currency_switch_actually_changes_the_currency(): void
    {
        $this->get('/currency/syp')->assertRedirect();
        $this->assertSame('syp', session('currency'));
    }

    // ═══════════════ الروابط المولَّدة ═══════════════

    /**
     * ⚠️ الرابط المطلق للمنتج هو ما يُرسل على واتساب. ولو أخذ مضيفه من عنوان
     * الطلب لما فتحه أحد: من يفتح اللوحة على `127.0.0.1` يُرسل `127.0.0.1`،
     * وهي لا تُفتح من هاتف أبدًا.
     *
     * ولم يُثبَّت المضيف عالميًا بـ`URL::forceRootUrl` لأن ذلك يكسر الدخول —
     * فالتثبيت موضعي في `ProductInquiry::url` وحدها.
     */
    public function test_the_product_url_takes_its_host_from_the_app_url(): void
    {
        config(['app.url' => 'https://nadaf.store']);

        $url = \App\Support\ProductInquiry::url($this->product);

        $this->assertSame('https://nadaf.store/p/silk-tie', $url);
    }

    /**
     * ولو ثُبِّت المضيف عالميًا (`URL::forceRootUrl`) لصارت التحويلات مطلقة
     * إلى `APP_URL`: يُحوَّل المتصفح إلى مضيف آخر، وكوكي الجلسة على المضيف
     * الأول — فيجد المستخدم نفسه خارج حسابه بعد كل دخول. فهذا الاختبار
     * يمنع عودة التثبيت العالمي: طلبٌ بمضيف مختلف يجب أن تُبنى روابطه به.
     */
    public function test_the_rest_of_the_app_follows_the_request_host(): void
    {
        $this->get('http://nadaf.test/login')->assertOk();

        $this->assertStringContainsString('nadaf.test', route('login'));
    }

    /**
     * الصفحات التي كانت تُحوّل إلى الدخول لأن قوالبها غير موجودة — صارت تعمل.
     * ولو عاد القالب يُحذف لسقط هذا الاختبار فورًا.
     */
    public function test_the_password_reset_pages_are_actually_implemented(): void
    {
        $this->assertTrue(view()->exists('auth.forgot-password'), 'قالب طلب الاستعادة مفقود');
        $this->assertTrue(view()->exists('auth.reset-password'), 'قالب تعيين كلمة المرور مفقود');

        $this->get('/forgot-password')->assertOk();

        $token = Password::createToken($this->customer);
        $this->get('/reset-password/'.$token.'?email='.$this->customer->email)->assertOk();
    }

    /** ورابط الاستعادة يجب أن يكون على صفحة الدخول، وإلا فالميزة مخفية */
    public function test_the_login_page_links_to_the_reset_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('password.request'), escape: false);
    }
}
