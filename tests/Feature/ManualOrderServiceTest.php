<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ManualOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * اختبارات «+ طلب يدوي» — الطلبات الهاتفية وزوار المحل.
 *
 * الأهم هنا أن الطلب اليدوي **ليس مسارًا موازيًا** للمحاسبة: يجب أن يُنتج
 * نفس الأثر الذي يُنتجه طلب الموقع — نفس الأكواد، نفس خصم المخزون، نفس
 * سجل الحالة، ونفس تثبيت سعر الصرف. أي انحراف يعني دفترين لا يُطابقان.
 */
class ManualOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithStock(int $quantity, float $price = 10.0): ProductVariant
    {
        $category = Category::create([
            'name_ar' => 'كرفات',
            'name_en' => 'Ties',
            'slug' => 'ties-'.uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة حرير',
            'name_en' => 'Silk Tie',
            'slug' => 'tie-'.uniqid(),
            'price_usd' => $price,
            'cost_usd' => 6,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'كحلي',
            'size' => 'طويل',
            'quantity' => $quantity,
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    // ---------- الحساب ----------

    public function test_it_creates_an_order_with_correct_totals(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(10, 12.0);

        $order = ManualOrderService::place([
            'customer_name' => 'أحمد الخطيب',
            'customer_phone' => '0999111222',
            'items' => [['variant_id' => $variant->id, 'quantity' => 3]],
            'shipping_method' => 'local',
            'shipping_usd' => 5,
            'discount_usd' => 1,
        ]);

        // 12 × 3 = 36 − 1 خصم + 5 شحن = 40
        $this->assertSame(36.0, (float) $order->subtotal_usd);
        $this->assertSame(1.0, (float) $order->discount_usd);
        $this->assertSame(5.0, (float) $order->shipping_usd);
        $this->assertSame(40.0, (float) $order->total_usd);

        // سعر الصرف مثبَّت لحظة الإنشاء، والليرة محسوبة منه
        $this->assertSame((float) setting('exchange_rate', 15000), (float) $order->exchange_rate);
        $this->assertSame(syp_from_usd(40.0), (float) $order->total_syp);

        $this->assertSame('pending', $order->status);
        $this->assertFalse($order->isPaid());
        $this->assertStringStartsWith('NDF-', $order->order_code);
    }

    public function test_pickup_has_no_shipping_fee_even_if_a_fee_was_sent(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $order = ManualOrderService::place([
            'customer_name' => 'زائر المحل',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'shipping_method' => 'pickup',
            'shipping_usd' => 9, // يجب أن يُهمَل
        ]);

        $this->assertSame(0.0, (float) $order->shipping_usd);
        $this->assertSame(10.0, (float) $order->total_usd);
    }

    public function test_a_manual_price_overrides_the_product_price(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5, 10.0);

        $order = ManualOrderService::place([
            'customer_name' => 'مفاوض',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'unit_price_usd' => 7.5]],
            'shipping_method' => 'pickup',
        ]);

        $this->assertSame(15.0, (float) $order->subtotal_usd);
        $this->assertSame(7.5, (float) $order->items()->first()->unit_price_usd);
    }

    // ---------- المخزون ----------

    public function test_it_deducts_stock_and_records_a_sale_movement(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(10);

        $order = ManualOrderService::place([
            'customer_name' => 'عميل',
            'items' => [['variant_id' => $variant->id, 'quantity' => 4]],
        ]);

        $this->assertSame(6, (int) $variant->fresh()->quantity);

        $movement = StockMovement::where('order_id', $order->id)->first();

        $this->assertNotNull($movement);
        $this->assertSame('sale', $movement->type);
        $this->assertSame(-4, (int) $movement->quantity);
        $this->assertSame(10, (int) $movement->balance_before);
        $this->assertSame(6, (int) $movement->balance_after);
    }

    public function test_insufficient_stock_is_rejected_and_nothing_is_written(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(2);

        $this->expectException(ValidationException::class);

        try {
            ManualOrderService::place([
                'customer_name' => 'طالب كمية كبيرة',
                'items' => [['variant_id' => $variant->id, 'quantity' => 5]],
            ]);
        } finally {
            // لا طلب ولا حركة ولا خصم مخزون — المعاملة أُلغيت كاملة
            $this->assertSame(0, Order::count());
            $this->assertSame(0, StockMovement::count());
            $this->assertSame(2, (int) $variant->fresh()->quantity);
        }
    }

    // ---------- التحقق ----------

    public function test_an_order_without_items_is_rejected(): void
    {
        $this->actingAs($this->staff());

        $this->expectException(ValidationException::class);

        ManualOrderService::place(['customer_name' => 'بلا منتجات', 'items' => []]);
    }

    public function test_items_with_zero_quantity_are_rejected(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $this->expectException(ValidationException::class);

        ManualOrderService::place([
            'customer_name' => 'كمية صفر',
            'items' => [['variant_id' => $variant->id, 'quantity' => 0]],
        ]);
    }

    public function test_customer_details_are_optional_and_fall_back_to_a_cash_customer(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        // بلا اسم ولا هاتف ولا بريد — بيع نقدي مباشر في المحل
        $order = ManualOrderService::place([
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'shipping_method' => 'pickup',
        ]);

        $this->assertSame('عميل نقدي', $order->user->name);
        $this->assertSame('customer', $order->user->role);
        $this->assertSame('pending', $order->status);
    }

    public function test_all_cash_orders_share_one_customer_account(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(10);

        $first = ManualOrderService::place([
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);
        $second = ManualOrderService::place([
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertSame($first->user_id, $second->user_id);
        // حساب نقدي واحد فقط، لا حساب لكل طلب
        $this->assertSame(1, User::where('email', 'walkin@nadaf.local')->count());
    }

    /**
     * الخطأ الذي أبلغ عنه المالك: كتابة بريد عميل مسجّل في طلب يدوي
     * كانت تُسقط الطلب كله بـ UNIQUE constraint failed: users.email.
     */
    public function test_an_email_belonging_to_an_existing_user_is_linked_not_duplicated(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $registered = User::factory()->create([
            'role' => 'customer',
            'name' => 'عميل مسجّل',
            'email' => 'known@example.com',
            'phone' => '0999000000',
        ]);

        $countBefore = User::count();

        $order = ManualOrderService::place([
            'customer_name' => 'اسم مكتوب يدويًا',
            'customer_email' => 'known@example.com',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        // يُربط بالحساب القائم ولا يُنشأ حساب ثانٍ بالبريد نفسه
        $this->assertSame($registered->id, $order->user_id);
        $this->assertSame($countBefore, User::count());
    }

    public function test_an_email_belonging_to_staff_is_linked_without_crashing(): void
    {
        $actor = $this->staff();
        $this->actingAs($actor);
        $variant = $this->variantWithStock(5);

        // الحالة الواقعية: الأدمن كتب بريده هو في حقل العميل
        $order = ManualOrderService::place([
            'customer_email' => $actor->email,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertSame($actor->id, $order->user_id);
    }

    public function test_email_matching_wins_over_phone_matching(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $byEmail = User::factory()->create(['role' => 'customer', 'email' => 'first@example.com', 'phone' => '0111']);
        User::factory()->create(['role' => 'customer', 'email' => 'second@example.com', 'phone' => '0222']);

        // البريد يخص الأول والهاتف يخص الثاني ⇒ البريد أرجح (عمود فريد)
        $order = ManualOrderService::place([
            'customer_email' => 'first@example.com',
            'customer_phone' => '0222',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertSame($byEmail->id, $order->user_id);
    }

    public function test_a_phone_only_customer_still_gets_a_usable_account(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $order = ManualOrderService::place([
            'customer_phone' => '0999888777',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertSame('0999888777', $order->user->phone);
        $this->assertSame('عميل نقدي', $order->user->name);
        $this->assertStringEndsWith('@nadaf.local', $order->user->email);
    }

    // ---------- العميل ----------

    public function test_a_phone_customer_gets_a_lightweight_account(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $order = ManualOrderService::place([
            'customer_name' => 'عميل هاتفي',
            'customer_phone' => '0999888777',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $customer = $order->user;

        $this->assertSame('عميل هاتفي', $customer->name);
        $this->assertSame('0999888777', $customer->phone);
        $this->assertSame('customer', $customer->role);
        $this->assertStringEndsWith('@nadaf.local', $customer->email);
    }

    public function test_the_same_phone_does_not_create_a_duplicate_account(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(10);

        $first = ManualOrderService::place([
            'customer_name' => 'عميل هاتفي',
            'customer_phone' => '0999888777',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $second = ManualOrderService::place([
            'customer_name' => 'عميل هاتفي',
            'customer_phone' => '0999888777',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertSame($first->user_id, $second->user_id);
        $this->assertSame(1, User::where('phone', '0999888777')->count());
    }

    public function test_an_existing_registered_customer_can_be_selected(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);
        $existing = User::factory()->create(['role' => 'customer', 'name' => 'عميل مسجّل']);

        $countBefore = User::count();

        $order = ManualOrderService::place([
            'user_id' => $existing->id,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertSame($existing->id, $order->user_id);
        // لم يُنشأ حساب جديد — العميل المسجّل يُستخدم كما هو
        $this->assertSame($countBefore, User::count());
    }

    // ---------- الأختام ----------

    public function test_confirming_payment_stamps_green_and_moves_to_preparing(): void
    {
        $actor = $this->staff();
        $this->actingAs($actor);
        $variant = $this->variantWithStock(5);

        $order = ManualOrderService::place([
            'customer_name' => 'دفع في المحل',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'confirm_payment' => true,
        ]);

        $this->assertTrue($order->isPaid());
        $this->assertSame($actor->id, $order->payment_confirmed_by);
        $this->assertSame('preparing', $order->status);
        $this->assertSame('green', $order->stampState());
        $this->assertFalse($order->isDelivered());
    }

    public function test_without_confirming_payment_the_order_stays_unstamped(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $order = ManualOrderService::place([
            'customer_name' => 'لم يدفع بعد',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $this->assertFalse($order->isPaid());
        $this->assertNull($order->payment_confirmed_at);
        $this->assertSame('none', $order->stampState());
        $this->assertSame('pending', $order->status);
    }

    // ---------- السجل ----------

    public function test_the_status_history_records_creation_and_the_stamp(): void
    {
        $this->actingAs($this->staff());
        $variant = $this->variantWithStock(5);

        $order = ManualOrderService::place([
            'customer_name' => 'عميل',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'confirm_payment' => true,
        ]);

        $history = $order->statusHistory()->get();

        $this->assertCount(2, $history);
        $this->assertSame('pending', $history[0]->to_status);
        $this->assertSame('preparing', $history[1]->to_status);
        $this->assertStringContainsString('قبض الدفع', (string) $history[1]->note);
    }

    public function test_multiple_items_are_all_deducted_and_priced(): void
    {
        $this->actingAs($this->staff());
        $a = $this->variantWithStock(10, 10.0);
        $b = $this->variantWithStock(10, 25.0);

        $order = ManualOrderService::place([
            'customer_name' => 'عميل بقطعتين',
            'items' => [
                ['variant_id' => $a->id, 'quantity' => 2],
                ['variant_id' => $b->id, 'quantity' => 1],
            ],
            'shipping_method' => 'pickup',
        ]);

        $this->assertSame(45.0, (float) $order->subtotal_usd);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(8, (int) $a->fresh()->quantity);
        $this->assertSame(9, (int) $b->fresh()->quantity);
        $this->assertSame(2, StockMovement::count());
    }
}
