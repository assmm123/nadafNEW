<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoiceController;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * الفاتورة: نصّها المُرسل على واتساب، وأختامها.
 *
 * ── العلّتان اللتان يمنعهما هذا الملف ──
 * ١. نصّ الفاتورة كان مختصرًا: كود وتاريخ واسم العميل وأصناف بإجماليها فقط —
 *    بلا هاتف ولا عنوان ولا سعر وحدة ولا خصم مفصّل. والمطلوب بيانات كاملة.
 * ٢. الأختام المرفوعة من الإعدادات كانت **وعدًا غير منفَّذ**: الحقل يقول
 *    «تُستخدم على الفاتورة بدل الختم المرسوم»، والفاتورة كانت ترسم ختمًا
 *    نصيًّا دائمًا ولا تقرأ الصورة إطلاقًا.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties-'.uniqid()]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة حرير',
            'name_en' => 'Silk Tie',
            'slug' => 'silk-'.uniqid(),
            'price_usd' => 10,
            'cost_usd' => 6,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'كحلي',
            'size' => 'L',
            'quantity' => 5,
        ]);

        $customer = User::factory()->create([
            'name' => 'سامر',
            'phone' => '0987654365',
            'email' => 'samer@example.com',
            'role' => 'customer',
        ]);

        $order = Order::create(array_merge([
            'user_id' => $customer->id,
            'order_code' => Order::generateCode(),
            'status' => 'pending',
            'subtotal_usd' => 20,
            'discount_usd' => 2,
            'shipping_usd' => 3,
            'total_usd' => 21,
            'exchange_rate' => 15000,
            'total_syp' => 315000,
            'shipping_method' => 'local',
            'city' => 'دمشق',
            'shipping_address' => 'المزة — شارع الجلاء، بناء ١٢',
            'notes' => 'يرجى الاتصال قبل التسليم',
        ], $attributes));

        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'name_ar' => 'كرافتة حرير',
            'name_en' => 'Silk Tie',
            'color' => 'كحلي',
            'size' => 'L',
            'quantity' => 2,
            'unit_price_usd' => 10,
            'unit_cost_usd' => 6,
            'total_price_usd' => 20,
            'is_wholesale' => false,
        ]);

        return $order->fresh(['items', 'user', 'paymentMethod', 'coupon']);
    }

    /** يستخرج نصّ الرسالة من رابط wa.me */
    private function text(Order $order): string
    {
        $link = InvoiceController::whatsappLink($order);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $params);

        return urldecode($params['text'] ?? '');
    }

    // ═══════════════ اكتمال البيانات ═══════════════

    public function test_the_message_carries_the_customer_contact_details(): void
    {
        $text = $this->text($this->order());

        $this->assertStringContainsString('سامر', $text);
        $this->assertStringContainsString('0987654365', $text, 'هاتف العميل');
        $this->assertStringContainsString('samer@example.com', $text, 'بريده');
    }

    public function test_the_message_carries_the_shipping_details(): void
    {
        $text = $this->text($this->order());

        $this->assertStringContainsString('توصيل محلي', $text, 'تسمية طريقة التوصيل لا رمزها');
        $this->assertStringContainsString('دمشق', $text);
        $this->assertStringContainsString('المزة', $text, 'العنوان كاملًا');
    }

    public function test_the_message_carries_unit_price_and_variant_specs(): void
    {
        $text = $this->text($this->order());

        $this->assertStringContainsString('كحلي / L', $text, 'مواصفة الصنف');
        $this->assertStringContainsString('2 × $10.00 = $20.00', $text, 'الكمية وسعر الوحدة والإجمالي');
    }

    public function test_the_message_carries_the_full_breakdown(): void
    {
        $text = $this->text($this->order());

        $this->assertStringContainsString('المجموع الفرعي: $20.00', $text);
        $this->assertStringContainsString('الخصم', $text);
        $this->assertStringContainsString('−$2.00', $text);
        $this->assertStringContainsString('الشحن: $3.00', $text);
        $this->assertStringContainsString('$21.00', $text, 'الإجمالي');
        $this->assertStringContainsString('315,000', $text, 'الإجمالي بالليرة');
        $this->assertStringContainsString('سعر الصرف', $text);
    }

    public function test_the_message_carries_the_order_notes(): void
    {
        $this->assertStringContainsString('يرجى الاتصال قبل التسليم', $this->text($this->order()));
    }

    public function test_the_message_states_the_payment_state(): void
    {
        $cod = PaymentMethod::create([
            'type' => 'cash_on_delivery', 'name' => 'الدفع عند الاستلام',
            'value' => '-', 'is_active' => true, 'requires_proof' => false,
        ]);

        $text = $this->text($this->order(['payment_method_id' => $cod->id]));

        $this->assertStringContainsString('الدفع عند الاستلام', $text);
        $this->assertStringContainsString('الدفع عند التسليم', $text);
    }

    public function test_the_message_reports_an_attached_proof(): void
    {
        $bank = PaymentMethod::create([
            'type' => 'bank', 'name' => 'حوالة بنكية',
            'value' => '123', 'is_active' => true, 'requires_proof' => true,
        ]);

        $text = $this->text($this->order([
            'payment_method_id' => $bank->id,
            'payment_reference' => 'TRX-9911',
            'payment_sender_name' => 'سمير الحلبي',
            'payment_proof_path' => 'payment-proofs/x.jpg',
        ]));

        $this->assertStringContainsString('حوالة بنكية', $text);
        $this->assertStringContainsString('TRX-9911', $text);
        $this->assertStringContainsString('سمير الحلبي', $text);
        $this->assertStringContainsString('مرفق', $text, 'الإثبات مرفق');
    }

    public function test_the_message_warns_when_the_proof_is_still_pending(): void
    {
        $bank = PaymentMethod::create([
            'type' => 'bank', 'name' => 'حوالة بنكية',
            'value' => '123', 'is_active' => true, 'requires_proof' => true,
        ]);

        $text = $this->text($this->order(['payment_method_id' => $bank->id]));

        $this->assertStringContainsString('بانتظار المراجعة', $text);
    }

    // ═══════════════ تطبيع رقم العميل ═══════════════

    /**
     * رقم محلي مثل `0987654365` كان يُنتج `wa.me/0987654365` — رابطًا لا يفتح،
     * لأن أول خانتين تُقرآن مفتاحَ دولة. فيُستبدل صفره بمفتاح الدولة من الإعدادات.
     */
    public function test_a_local_customer_phone_is_converted_to_international(): void
    {
        Setting::set('wa_country_code', '963');

        $link = InvoiceController::whatsappLink($this->order());

        $this->assertStringStartsWith('https://wa.me/963987654365?text=', $link);
    }

    public function test_a_number_already_international_is_left_alone(): void
    {
        Setting::set('wa_country_code', '963');

        $link = InvoiceController::whatsappLink($this->order(['user_id' => User::factory()->create([
            'phone' => '+963930322406',
            'role' => 'customer',
        ])->id]));

        $this->assertStringStartsWith('https://wa.me/963930322406?text=', $link);
    }

    public function test_the_helper_normalises_the_known_phone_shapes(): void
    {
        Setting::set('wa_country_code', '963');

        $this->assertSame('963930322406', wa_digits('00963930322406'), 'بادئة اتصال دولي');
        $this->assertSame('963930322406', wa_digits('+963 930 322 406'), 'رموز وفراغات');
        $this->assertSame('963987654365', wa_digits('0987654365'), 'صفر محلي');
        $this->assertSame('963930322406', wa_digits('963930322406'), 'دولي كما هو');
        $this->assertSame('', wa_digits(null));
        $this->assertSame('', wa_digits('لا رقم'));
    }

    /** بلا مفتاح دولة مضبوط لا يُخمَّن شيء — الرقم يُترك كما هو */
    public function test_no_country_code_is_guessed_when_unset(): void
    {
        Setting::set('wa_country_code', '');

        $this->assertSame('0987654365', wa_digits('0987654365'),
            'مفتاح خاطئ أسوأ من رابط لا يفتح — فلا تخمين');
    }

    // ═══════════════ الأختام ═══════════════

    public function test_the_uploaded_stamps_are_printed_on_the_invoice(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/green.png', 'x');
        Storage::disk('public')->put('branding/gold.png', 'x');

        Setting::set('stamp_green_path', 'branding/green.png');
        Setting::set('stamp_gold_path', 'branding/gold.png');

        $order = $this->order([
            'payment_confirmed_at' => now(),
            'stamped_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'owner']);
        $html = $this->actingAs($admin)
            ->get(route('admin.invoice', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('branding/green.png', $html, 'الختم الأخضر المرفوع');
        $this->assertStringContainsString('branding/gold.png', $html, 'الختم الذهبي المرفوع');
        $this->assertStringNotContainsString('✓ الختم الأخضر', $html, 'المرسوم يُستبدل لا يُضاف');
    }

    public function test_the_drawn_stamp_is_used_when_nothing_was_uploaded(): void
    {
        Storage::fake('public');
        Setting::set('stamp_green_path', '');
        Setting::set('stamp_gold_path', '');

        $order = $this->order([
            'payment_confirmed_at' => now(),
            'stamped_at' => now(),
        ]);

        $html = $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('admin.invoice', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('✓ الختم الأخضر', $html, 'يعود المرسوم');
        // نفحص وسم الصورة لا اسم الصنف: `.stp-img` معرَّف في كتلة الأنماط
        // فيظهر في كل صفحة، فالبحث عنه وحده لا يعني أن صورةً رُسمت.
        $this->assertStringNotContainsString('class="stp-img"', $html, 'ولا صورة ختم');
    }
}
