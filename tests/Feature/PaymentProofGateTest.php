<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * بوابة إثبات الدفع.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * كان التحقق مكتوبًا في واجهة الدفع وحدها، ومكرّرًا في مكوّنين. وخدمة إنشاء
 * الطلب (`CheckoutService::place`) **لا تتحقق من شيء**، فتولّد كود الطلب لكل
 * من يصلها. وكانت وسيلتان في القاعدة الحيّة معفاة من الإثبات بلا سبب
 * (`wallet/syriatel` و`bank/رستم باد`)، فيمرّ الطلب بلا إثبات دفع.
 *
 * فهذه الاختبارات تقيس **البوابة نفسها** لا الواجهة: تنادي الخدمة مباشرةً،
 * وهي الطريق الذي كان مفتوحًا.
 */
class PaymentProofGateTest extends TestCase
{
    use RefreshDatabase;

    private function method(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'type' => 'bank',
            'name' => 'حوالة بنكية',
            'value' => '123',
            'is_active' => true,
            'requires_proof' => true,
        ], $attributes));
    }

    /** منتج بمخزون في السلة — يعيد المتغيّر لفحص المخزون بعده */
    private function cart(int $quantity = 10, int $qty = 1): ProductVariant
    {
        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties-'.uniqid()]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة',
            'name_en' => 'Tie',
            'slug' => 'tie-'.uniqid(),
            'price_usd' => 10,
            'cost_usd' => 6,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'low_stock_threshold' => 3,
        ]);

        Session::put(CartService::SESSION_KEY, [
            'v'.$variant->id => ['key' => 'v'.$variant->id, 'qty' => $qty],
        ]);

        return $variant;
    }

    private function place(array $overrides = []): Order
    {
        return CheckoutService::place(
            User::factory()->create(),
            array_merge(['shipping_method' => 'pickup'], $overrides),
        );
    }

    // ═══════════════ الرفض ═══════════════

    public function test_a_method_that_requires_proof_refuses_an_order_without_it(): void
    {
        $method = $this->method();
        $variant = $this->cart();

        try {
            $this->place(['payment_method_id' => $method->id]);
            $this->fail('كان يجب رفض الطلب — لا إثبات دفع');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_reference', $e->errors());
        }

        // والأهم: لا أثر للطلب — لا كود ولا خصم مخزون
        $this->assertSame(0, Order::count(), 'لا يُولَّد كود طلب بلا إثبات');
        $this->assertSame(10, (int) $variant->fresh()->quantity, 'ولا يُخصم مخزون');
    }

    public function test_choosing_no_method_is_refused_when_methods_exist(): void
    {
        $this->method();
        $this->cart();

        try {
            $this->place();
            $this->fail('كان يجب رفض الطلب — لم تُحدَّد وسيلة دفع');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method_id', $e->errors());
        }

        $this->assertSame(0, Order::count());
    }

    public function test_a_blank_reference_does_not_count_as_proof(): void
    {
        $method = $this->method();
        $this->cart();

        $this->expectException(ValidationException::class);

        $this->place(['payment_method_id' => $method->id, 'payment_reference' => '   ']);
    }

    // ═══════════════ القبول ═══════════════

    public function test_a_receipt_number_alone_is_enough(): void
    {
        $method = $this->method();
        $this->cart();

        $order = $this->place([
            'payment_method_id' => $method->id,
            'payment_reference' => 'TRX-99881',
        ]);

        $this->assertNotNull($order->order_code);
        $this->assertSame('TRX-99881', $order->payment_reference);
    }

    public function test_a_proof_file_alone_is_enough(): void
    {
        $method = $this->method();
        $this->cart();

        $order = $this->place([
            'payment_method_id' => $method->id,
            'payment_proof_path' => 'payment-proofs/receipt.jpg',
        ]);

        $this->assertNotNull($order->order_code);
        $this->assertSame('payment-proofs/receipt.jpg', $order->payment_proof_path);
    }

    /** الإعفاء الوحيد بطبيعة الحال: لا حوالة تُرفق بالدفع عند التسليم */
    public function test_cash_on_delivery_passes_without_any_proof(): void
    {
        $cod = $this->method([
            'type' => 'cash_on_delivery',
            'name' => 'الدفع عند الاستلام',
            'requires_proof' => false,
        ]);
        $this->cart();

        $order = $this->place(['payment_method_id' => $cod->id]);

        $this->assertNotNull($order->order_code);
        $this->assertNull($order->payment_reference);
    }

    /** ولو طلب المالك إثباتًا على الدفع عند التسليم احتُرم اختياره */
    public function test_the_owner_can_demand_proof_even_on_cash_on_delivery(): void
    {
        $cod = $this->method([
            'type' => 'cash_on_delivery',
            'name' => 'الدفع عند الاستلام',
            'requires_proof' => true,
        ]);
        $this->cart();

        $this->expectException(ValidationException::class);

        $this->place(['payment_method_id' => $cod->id]);
    }

    // ═══════════════ الافتراضي ═══════════════

    /**
     * الوسيلة الجديدة **تطلب الإثبات** حتى يُقال لها غير ذلك. وكان الافتراضي
     * `false`، فتُعفى صامتة — وهو أصل الثغرة.
     */
    public function test_a_new_method_requires_proof_by_default(): void
    {
        $method = PaymentMethod::create([
            'type' => 'wallet',
            'name' => 'محفظة',
            'value' => '0999',
            'is_active' => true,
        ]);

        $this->assertTrue((bool) $method->fresh()->requires_proof,
            'الافتراضي يجب أن يكون الأمان لا الإعفاء');
    }

    public function test_only_cash_on_delivery_is_exempt_by_nature(): void
    {
        $this->assertSame(['cash_on_delivery'], PaymentProof::EXEMPT_TYPES);
    }

    public function test_the_helper_reads_the_flag(): void
    {
        $this->assertTrue(PaymentProof::requiresProof($this->method()->id));

        $cod = $this->method(['type' => 'cash_on_delivery', 'requires_proof' => false]);
        $this->assertFalse(PaymentProof::requiresProof($cod->id));

        $this->assertFalse(PaymentProof::requiresProof(null));
    }

    /** القاعدة الواحدة تُبنى في مكان واحد فلا تتباعد بين المكوّنين */
    public function test_the_shared_rules_switch_on_the_flag(): void
    {
        $required = PaymentProof::proofRules(true);
        $optional = PaymentProof::proofRules(false);

        $this->assertContains('required_without:proof', $required['payment_reference']);
        $this->assertNotContains('required_without:proof', $optional['payment_reference']);
        $this->assertSame($required['proof'], $optional['proof'], 'قواعد الملف واحدة في الحالتين');
    }
}
