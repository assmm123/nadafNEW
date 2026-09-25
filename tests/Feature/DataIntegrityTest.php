<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * ═══ سلامة البيانات ═══
 *
 * أخطاء الجمع والخصم لا تظهر عند الاستخدام — تظهر عند **مراجعة الدفاتر**.
 * وهذه الاختبارات تقيس **العلاقات** لا الأرقام الثابتة: مجموع البنود = المجموع
 * الفرعي، والإجمالي = المجموع − الخصم + الشحن. فتبقى صحيحة لو تغيّرت الأسعار
 * أو الضرائب أو طرق الشحن.
 *
 * وتقيس أيضًا **الثبات التاريخي**: طلب قديم لا يجب أن يتغيّر لأن منتجًا
 * أُعيدت تسميته أو حُذف.
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $qty = 10, float $price = 25.0): ProductVariant
    {
        $category = Category::create(['name_ar' => 'ك', 'name_en' => 'C', 'slug' => 'c-'.uniqid()]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة', 'name_en' => 'Tie',
            'slug' => 'tie-'.uniqid(),
            'price_usd' => $price, 'cost_usd' => $price / 2,
            'internal_code' => 'TIE-01',
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'كحلي', 'size' => 'L', 'sku' => 'TIE-01-NVY',
            'quantity' => $qty, 'low_stock_threshold' => 3,
        ]);
    }

    private function cart(ProductVariant $variant, int $qty): void
    {
        Session::put(CartService::SESSION_KEY, [
            'v'.$variant->id => ['key' => 'v'.$variant->id, 'qty' => $qty],
        ]);
    }

    private function place(array $overrides = []): Order
    {
        return CheckoutService::place(User::factory()->create(), array_merge([
            'shipping_method' => 'pickup',
        ], $overrides));
    }

    // ═══════════════ ١. العلاقات الحسابية ═══════════════

    public function test_the_total_equals_subtotal_minus_discount_plus_shipping(): void
    {
        $v = $this->variant(10, 25);
        $this->cart($v, 3);

        $order = $this->place();

        $expected = round($order->subtotal_usd - $order->discount_usd + $order->shipping_usd, 2);

        $this->assertEqualsWithDelta($expected, (float) $order->total_usd, 0.01,
            'الإجمالي لا يساوي المجموع − الخصم + الشحن');
    }

    public function test_the_subtotal_equals_the_sum_of_the_lines(): void
    {
        $a = $this->variant(10, 25);
        $b = $this->variant(10, 40);

        Session::put(CartService::SESSION_KEY, [
            'v'.$a->id => ['key' => 'v'.$a->id, 'qty' => 2],
            'v'.$b->id => ['key' => 'v'.$b->id, 'qty' => 3],
        ]);

        $order = $this->place()->fresh('items');

        $sum = round($order->items->sum('total_price_usd'), 2);

        $this->assertEqualsWithDelta($sum, (float) $order->subtotal_usd, 0.01,
            'المجموع الفرعي لا يساوي مجموع البنود');
    }

    /** والخصم يخفض الإجمالي فعلًا — لا يُعرض بلا أثر */
    public function test_a_coupon_actually_reduces_the_total(): void
    {
        $v = $this->variant(10, 100);
        $this->cart($v, 1);

        $plain = $this->place();

        Coupon::create([
            'code' => 'SAVE10', 'discount_type' => 'fixed', 'discount_value' => 10,
            'is_active' => true, 'used_count' => 0,
        ]);

        // الطلب الأول فرّغ السلة — تُعاد تعبئتها قبل الطلب الثاني
        $this->cart($v, 1);

        $discounted = $this->place(['coupon_code' => 'SAVE10']);

        $this->assertGreaterThan(0, (float) $discounted->discount_usd, 'الخصم صفر');
        $this->assertEqualsWithDelta(
            (float) $plain->total_usd - 10,
            (float) $discounted->total_usd,
            0.01,
            'الإجمالي لم ينقص بمقدار الخصم',
        );
    }

    /** والسعر بالليرة يتبع سعر الصرف المسجَّل في الطلب لا الحالي */
    public function test_the_syp_total_matches_the_order_exchange_rate(): void
    {
        $v = $this->variant(10, 100);
        $this->cart($v, 1);

        $order = $this->place();

        $this->assertEqualsWithDelta(
            (float) $order->total_usd * (float) $order->exchange_rate,
            (float) $order->total_syp,
            1,
            'الإجمالي بالليرة لا يطابق سعر الصرف المثبَّت',
        );
    }

    // ═══════════════ ٢. المخزون ═══════════════

    public function test_stock_is_deducted_exactly_once_per_order(): void
    {
        $v = $this->variant(10);
        $this->cart($v, 3);

        $this->place();

        $this->assertSame(7, (int) $v->fresh()->quantity);
    }

    public function test_stock_never_goes_negative(): void
    {
        $v = $this->variant(2);
        $this->cart($v, 5);

        try {
            $this->place();
        } catch (\Throwable) {
            // الرفض متوقّع — المهم ألّا يصير المخزون سالبًا
        }

        $this->assertGreaterThanOrEqual(0, (int) $v->fresh()->quantity,
            'المخزون صار سالبًا');
    }

    public function test_a_refused_order_leaves_no_trace(): void
    {
        $v = $this->variant(1);
        $this->cart($v, 5);

        $before = Order::count();

        try {
            $this->place();
        } catch (\Throwable) {
        }

        $this->assertSame($before, Order::count(), 'أُنشئ طلب مرفوض');
        $this->assertSame(1, (int) $v->fresh()->quantity, 'خُصم مخزون طلب مرفوض');
    }

    // ═══════════════ ٣. الثبات التاريخي ═══════════════

    /**
     * ⚠️ إعادة تسمية منتج لا يجب أن تُغيّر طلبًا قديمًا. والطلب يحفظ الاسم
     * والسعر **لحظة الشراء** — وهو المرجع في الفاتورة.
     */
    public function test_renaming_a_product_does_not_change_an_old_order(): void
    {
        $v = $this->variant(10, 25);
        $this->cart($v, 2);

        $order = $this->place()->fresh('items');
        $storedName = $order->items->first()->name_ar;
        $storedPrice = $order->items->first()->unit_price_usd;

        $v->product->update(['name_ar' => 'اسم جديد تمامًا', 'price_usd' => 999]);

        $fresh = $order->fresh('items');

        $this->assertSame($storedName, $fresh->items->first()->name_ar, 'اسم الطلب تغيّر');
        $this->assertEqualsWithDelta((float) $storedPrice, (float) $fresh->items->first()->unit_price_usd, 0.01,
            'سعر الطلب تغيّر بعد تعديل المنتج');
        $this->assertEqualsWithDelta((float) $order->total_usd, (float) $fresh->total_usd, 0.01);
    }

    /** وحذف منتج لا يُفسد الفواتير القديمة */
    public function test_deleting_a_product_keeps_the_order_readable(): void
    {
        $v = $this->variant(10, 25);
        $this->cart($v, 2);

        $order = $this->place()->fresh('items');

        $v->product->delete();

        $fresh = $order->fresh('items');

        $this->assertNotNull($fresh, 'الطلب اختفى بحذف المنتج');
        $this->assertNotEmpty($fresh->items, 'بنود الطلب اختفت');
        $this->assertNotNull($fresh->items->first()->name_ar, 'اسم البند ضاع');
    }

    // ═══════════════ ٤. الكوبون ═══════════════

    public function test_a_coupon_usage_counter_increments_once_per_order(): void
    {
        $v = $this->variant(10, 100);
        $this->cart($v, 1);

        $coupon = Coupon::create([
            'code' => 'ONCE', 'discount_type' => 'fixed', 'discount_value' => 5,
            'is_active' => true, 'used_count' => 0,
        ]);

        $this->place(['coupon_code' => 'ONCE']);

        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_a_coupon_beyond_its_usage_limit_is_refused(): void
    {
        $v = $this->variant(10, 100);
        $this->cart($v, 1);

        Coupon::create([
            'code' => 'LIMITED', 'discount_type' => 'fixed', 'discount_value' => 5,
            'is_active' => true, 'used_count' => 1, 'usage_limit' => 1,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->place(['coupon_code' => 'LIMITED']);
    }

    // ═══════════════ ٥. التفرد ═══════════════

    public function test_every_order_gets_a_unique_code(): void
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $v = $this->variant(10, 10);
            $this->cart($v, 1);
            $codes[] = $this->place()->order_code;
        }

        $this->assertSame(count($codes), count(array_unique($codes)), 'أكواد طلبات مكرّرة');
    }

    public function test_a_new_order_starts_pending_with_a_history_entry(): void
    {
        $v = $this->variant(10, 10);
        $this->cart($v, 1);

        $order = $this->place()->fresh('statusHistory');

        $this->assertSame('pending', $order->status);
        $this->assertCount(1, $order->statusHistory, 'لا سجلّ للحالة الأولى');
        $this->assertSame('pending', $order->statusHistory->first()->to_status);
    }
}
