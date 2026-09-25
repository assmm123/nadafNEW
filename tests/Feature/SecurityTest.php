<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ═══ الأمن ═══
 *
 * النظام مبنيّ بالسريع، والمسار السعيد يعمل. وهذه الاختبارات لا تفحص المسار
 * السعيد — تفحص **ما لم يتوقّعه الباني**: أن يجرّب أحدهم ما لم يُجرَّب.
 *
 * الفئات: الوصول غير المصرّح (IDOR) · تصعيد الصلاحيات · حقن السكربت ·
 * كشف البيانات · حماية مسارات الكتابة.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $customer;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'owner', 'name' => 'المالك']);
        $this->customer = User::factory()->create(['role' => 'customer', 'name' => 'سامر']);
        $this->stranger = User::factory()->create(['role' => 'customer', 'name' => 'غريب']);
    }

    private function orderFor(User $user, string $code = 'NDF-SECRET'): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_code' => $code,
            'status' => 'pending',
            'subtotal_usd' => 100, 'discount_usd' => 0, 'shipping_usd' => 0,
            'total_usd' => 100, 'exchange_rate' => 15000, 'total_syp' => 1500000,
            'shipping_method' => 'pickup',
            'shipping_address' => 'عنوان سري خاص بالعميل',
        ]);
    }

    // ═══════════════ ١. IDOR — الوصول غير المصرّح ═══════════════

    public function test_a_customer_cannot_open_another_customers_order(): void
    {
        $order = $this->orderFor($this->customer);

        $this->actingAs($this->stranger)
            ->get(route('account.order', $order->order_code))
            ->assertNotFound();
    }

    public function test_a_customer_cannot_open_another_customers_invoice(): void
    {
        $order = $this->orderFor($this->customer);

        $this->actingAs($this->stranger)
            ->get(route('account.invoice', $order->order_code))
            ->assertNotFound();
    }

    public function test_the_owner_of_an_order_can_open_it(): void
    {
        $order = $this->orderFor($this->customer);

        $this->actingAs($this->customer)
            ->get(route('account.order', $order->order_code))
            ->assertOk();
    }

    /** والضيف لا يرى صفحة نجاح طلب غيره ولو عرف الرمز */
    public function test_a_guest_cannot_open_a_strangers_success_page(): void
    {
        $order = $this->orderFor($this->customer);

        $this->get(route('checkout.success', $order->order_code))->assertNotFound();
    }

    public function test_the_account_area_is_closed_to_guests(): void
    {
        foreach (['account.profile', 'account.orders'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    // ═══════════════ ٢. تصعيد الصلاحيات ═══════════════

    /**
     * ⚠️ `role` و`status` في `User::$fillable`. فلو مرّ الطلب كاملًا إلى
     * `update()` لرقّى العميل نفسه إلى مالك بلا كلمة مرور ولا صلاحية.
     */
    public function test_a_customer_cannot_promote_themselves_via_the_profile_form(): void
    {
        $this->actingAs($this->customer)->post(route('account.profile.update'), [
            'name' => 'سامر',
            'phone' => '0987654365',
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->assertSame('customer', $this->customer->fresh()->role,
            'العميل رقّى نفسه إلى مالك!');
    }

    public function test_a_customer_cannot_promote_themselves_via_a_crafted_field(): void
    {
        $this->actingAs($this->customer)->post(route('account.profile.update'), [
            'name' => 'سامر',
            'phone' => '0987654321',
            'user' => ['role' => 'owner'],
            'is_admin' => true,
        ]);

        $this->assertFalse($this->customer->fresh()->isAdmin(),
            'حقل مُلفَّق رقّى الحساب إلى الطاقم');
    }

    public function test_a_blocked_staff_member_loses_panel_access_immediately(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get('/admin')->assertOk();

        $manager->update(['status' => 'blocked']);

        $this->actingAs($manager->fresh())->get('/admin')->assertForbidden();
    }

    // ═══════════════ ٣. حقن السكربت ═══════════════

    /**
     * اسم عميل أو ملاحظة تحتوي وسمًا — هل يُهرَّب في الفاتورة الإدارية؟
     * ولو مرّ خامًّا لصار كل من يفتح الفاتورة ضحية.
     */
    public function test_a_script_tag_in_customer_data_is_escaped_in_the_invoice(): void
    {
        // الاسم المعروض هو اسم **الحساب** (customerName يقدّمه على customer_name)
        $this->customer->update(['name' => '<script>alert(1)</script>']);

        $order = $this->orderFor($this->customer);
        $order->update(['shipping_address' => '"><img src=x onerror=alert(1)>']);

        $html = $this->actingAs($this->owner)
            ->get(route('admin.invoice', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html,
            'اسم العميل مرّ خامًّا في الفاتورة');
        $this->assertStringContainsString('&lt;script&gt;', $html, 'يُهرَّب لا يُحذف');
    }

    public function test_a_script_tag_in_a_product_name_is_escaped_on_the_storefront(): void
    {
        $category = Category::create(['name_ar' => 'ق', 'name_en' => 'C', 'slug' => 'c']);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => '<script>alert(1)</script>',
            'name_en' => 'x',
            'slug' => 'xss-product',
            'price_usd' => 10, 'is_active' => true,
        ]);

        $html = $this->get('/p/'.$product->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    /**
     * ورسالة تيليجرام تُهرَّب — وإلا رفضها تيليجرام بخطأ 400 وضاع الإشعار
     * بصمت (النداء داخل try/catch فلا يظهر خطأ).
     */
    public function test_the_telegram_message_escapes_user_input(): void
    {
        \Illuminate\Support\Facades\Http::fake();

        \App\Models\Setting::set('telegram_bot_token', '111:test');
        \App\Models\Setting::set('telegram_chat_id', '999');
        \App\Models\Setting::set('telegram_verify_ssl', '0');

        $this->customer->update(['name' => '<b>اسم</b> & <i>وسم</i>']);
        $order = $this->orderFor($this->customer);

        \App\Services\TelegramService::sendOrder($order->fresh(['items', 'user', 'paymentMethod']));

        $sent = '';
        \Illuminate\Support\Facades\Http::recorded(function ($request) use (&$sent) {
            $sent .= $request->body();

            return true;
        });

        $this->assertNotSame('', $sent, 'لم تُرسل رسالة');
        $this->assertStringContainsString('&lt;b&gt;', $sent, 'يُهرَّب قبل الإرسال');
        $this->assertStringNotContainsString('<b>اسم</b>', $sent, 'مرّ الوسم خامًّا ⇒ تيليجرام يرفض الرسالة');
    }

    // ═══════════════ ٤. كشف البيانات ═══════════════

    public function test_the_password_is_never_serialised(): void
    {
        $json = $this->customer->toJson();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }

    /** رمز الطلب لا يُتوقَّع بسهولة — وإلا عُدّدت الطلبات */
    public function test_order_codes_are_not_sequential_or_trivially_guessable(): void
    {
        $codes = [];
        for ($i = 0; $i < 25; $i++) {
            $codes[] = Order::generateCode();
        }

        $this->assertSame(25, count(array_unique($codes)), 'أكواد مكرّرة');

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^NDF-[A-Z0-9]{6}$/', $code);
            $this->assertGreaterThanOrEqual(6, strlen(explode('-', $code)[1]),
                'الجزء العشوائي قصير ⇒ قابل للتخمين');
        }
    }

    // ═══════════════ ٥. حماية مسارات الكتابة ═══════════════

    /**
     * كل مسار كتابة يجب أن يمرّ بـCSRF. والاختبار يمنع إضافة مسار POST
     * خارج مجموعة `web` التي تحمله.
     */
    public function test_write_routes_are_protected_by_csrf(): void
    {
        $routes = app('router')->getRoutes();
        $unprotected = [];

        foreach ($routes as $route) {
            $methods = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);

            if (! $methods) {
                continue;
            }

            $uri = $route->uri();

            // مسارات Filament وLivewire تحمل حمايتها الخاصة،
            // و`storage/{path}` مسار داخلي في Laravel لخدمة الملفات محليًّا.
            if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'livewire')
                || str_starts_with($uri, 'storage')) {
                continue;
            }

            if (! in_array('web', $route->middleware(), true)) {
                $unprotected[] = implode('|', $methods).' '.$uri;
            }
        }

        $this->assertSame([], $unprotected,
            "مسارات كتابة خارج حماية CSRF:\n- ".implode("\n- ", $unprotected));
    }
}
