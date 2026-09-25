<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات التقارير والأرباح.
 *
 * الأرقام هنا هي ما يبني عليه صاحب المتجر قراراته (وبياناته الضريبية).
 * أهم قاعدة يجب ضمانها: **الطلبات الملغاة لا تدخل في أي إحصاء** — وإلا
 * ظهرت مبيعات وهمية وأرباح لا وجود لها.
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::create([
            'name_ar' => 'كرفات',
            'name_en' => 'Ties',
            'slug' => 'ties',
        ]);
    }

    private function product(string $slug, string $name = 'كرافتة'): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name_ar' => $name,
            'name_en' => 'Tie',
            'slug' => $slug,
            'price_usd' => 10,
            'cost_usd' => 6,
        ]);
    }

    /**
     * @param  array<int, array{product?: Product, qty?: int, unit?: float, cost?: float|null}>  $items
     */
    private function order(array $attributes = [], array $items = []): Order
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ($item['unit'] ?? 10) * ($item['qty'] ?? 1);
        }

        $order = Order::create([
            'user_id' => $this->user->id,
            'order_code' => Order::generateCode(),
            'status' => $attributes['status'] ?? 'pending',
            'subtotal_usd' => $attributes['subtotal_usd'] ?? $subtotal,
            'discount_usd' => $attributes['discount_usd'] ?? 0,
            'shipping_usd' => $attributes['shipping_usd'] ?? 0,
            'total_usd' => $attributes['total_usd'] ?? $subtotal,
            'exchange_rate' => 15000,
            'total_syp' => $attributes['total_syp'] ?? round($subtotal * 15000, 2),
        ]);

        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => ($item['product'] ?? null)?->id,
                'name_ar' => ($item['product'] ?? null)?->name_ar ?? 'منتج',
                'name_en' => 'Item',
                'quantity' => $item['qty'] ?? 1,
                'unit_price_usd' => $item['unit'] ?? 10,
                'unit_cost_usd' => array_key_exists('cost', $item) ? $item['cost'] : 6,
                'total_price_usd' => ($item['unit'] ?? 10) * ($item['qty'] ?? 1),
                'is_wholesale' => false,
            ]);
        }

        if (isset($attributes['created_at'])) {
            $order->created_at = $attributes['created_at'];
            $order->save();
        }

        return $order;
    }

    private function today(): array
    {
        return [now()->startOfDay(), now()->endOfDay()];
    }

    // ---------- الملخص ----------

    public function test_cancelled_orders_are_excluded_from_every_figure(): void
    {
        $product = $this->product('tie-a');

        $this->order(['total_usd' => 100, 'subtotal_usd' => 100], [['product' => $product, 'qty' => 10]]);
        $this->order(['status' => 'cancelled', 'total_usd' => 999], [['product' => $product, 'qty' => 99]]);

        $summary = ReportService::summary(...$this->today());

        $this->assertSame(1, $summary['ordersCount'], 'الطلب الملغي لا يُحسب');
        $this->assertSame(100.0, $summary['salesUsd']);
        $this->assertSame(10, $summary['itemsSold']);
    }

    public function test_summary_computes_sales_syp_and_average(): void
    {
        $this->order(['total_usd' => 100, 'total_syp' => 1500000]);
        $this->order(['total_usd' => 50, 'total_syp' => 750000]);

        $summary = ReportService::summary(...$this->today());

        $this->assertSame(2, $summary['ordersCount']);
        $this->assertSame(150.0, $summary['salesUsd']);
        $this->assertSame(2250000.0, $summary['salesSyp']);
        $this->assertSame(75.0, $summary['avgUsd']);
    }

    public function test_profit_counts_only_items_with_a_known_cost(): void
    {
        $product = $this->product('tie-b');

        // عنصر بتكلفة: 10$ بيع − 6$ تكلفة = 4$ ربح على 2 قطعة = 8$
        $this->order([], [['product' => $product, 'qty' => 2, 'unit' => 10, 'cost' => 6]]);

        // عنصر بلا تكلفة: يُستبعد من الربح تمامًا (لا يُحسب كربح كامل)
        $this->order([], [['product' => $product, 'qty' => 1, 'unit' => 10, 'cost' => null]]);

        $summary = ReportService::summary(...$this->today());

        $this->assertSame(8.0, $summary['profitUsd']);
        $this->assertSame(1, $summary['itemsWithCost'], 'عنصر واحد فقط له تكلفة معروفة');
        $this->assertSame(30.0, $summary['salesUsd']);
    }

    public function test_summary_is_zero_when_there_are_no_orders(): void
    {
        $summary = ReportService::summary(...$this->today());

        $this->assertSame(0, $summary['ordersCount']);
        $this->assertSame(0.0, $summary['salesUsd']);
        $this->assertSame(0.0, $summary['avgUsd'], 'لا قسمة على صفر');
        $this->assertSame(0.0, $summary['profitUsd']);
    }

    public function test_stamped_orders_are_counted_separately(): void
    {
        $stamped = $this->order(['total_usd' => 10]);
        $stamped->update(['stamped_at' => now()]);

        $this->order(['total_usd' => 10]);

        $this->assertSame(1, ReportService::summary(...$this->today())['stampedCount']);
    }

    // ---------- التجميعات ----------

    public function test_sales_are_grouped_by_category(): void
    {
        $product = $this->product('tie-c');

        $this->order([], [['product' => $product, 'qty' => 3, 'unit' => 10, 'cost' => 6]]);
        $this->order([], [['product' => $product, 'qty' => 2, 'unit' => 10, 'cost' => 6]]);

        $rows = ReportService::byCategory(...$this->today());

        $this->assertCount(1, $rows);
        $this->assertSame('كرفات', $rows[0]['name']);
        $this->assertEquals(5, $rows[0]['qty']);
        $this->assertEquals(50.0, (float) $rows[0]['sales']);
        $this->assertEquals(20.0, (float) $rows[0]['profit']);
    }

    public function test_sales_are_grouped_by_product_with_limit(): void
    {
        $a = $this->product('tie-d', 'كرافتة أ');
        $b = $this->product('tie-e', 'كرافتة ب');

        $this->order([], [['product' => $a, 'qty' => 5]]);
        $this->order([], [['product' => $b, 'qty' => 2]]);

        $rows = ReportService::byProduct(...$this->today());

        $this->assertCount(2, $rows);
        $this->assertSame('كرافتة أ', $rows[0]['name'], 'مرتّبة تنازليًا بالمبيعات');
        $this->assertEquals(5, $rows[0]['qty']);

        $this->assertCount(1, ReportService::byProduct($this->today()[0], $this->today()[1], 1));
    }

    public function test_daily_log_groups_orders_by_date(): void
    {
        $this->order(['total_usd' => 40]);
        $this->order(['total_usd' => 60]);
        $this->order(['total_usd' => 25, 'created_at' => now()->subDay()]);

        $rows = ReportService::daily(now()->subDays(2)->startOfDay(), now()->endOfDay());

        $this->assertCount(2, $rows);

        $byDay = collect($rows)->keyBy('day');
        $this->assertEquals(2, $byDay[now()->toDateString()]['orders']);
        $this->assertEquals(100.0, (float) $byDay[now()->toDateString()]['sales']);
        $this->assertEquals(1, $byDay[now()->subDay()->toDateString()]['orders']);
    }

    // ---------- النطاقات ----------

    public function test_resolve_range_returns_expected_windows(): void
    {
        [$from, $to] = ReportService::resolveRange('today');
        $this->assertTrue($from->isSameDay(now()));
        $this->assertTrue($to->isSameDay(now()));

        [$from7] = ReportService::resolveRange('last7');
        $this->assertTrue($from7->isSameDay(now()->subDays(6)));

        [$fromMonth] = ReportService::resolveRange('this_month');
        $this->assertTrue($fromMonth->isSameDay(now()->startOfMonth()));

        [$customFrom, $customTo] = ReportService::resolveRange('custom', '2026-01-05', '2026-01-09');
        $this->assertSame('2026-01-05 00:00:00', $customFrom->toDateTimeString());
        $this->assertSame('2026-01-09 23:59:59', $customTo->toDateTimeString());
    }

    public function test_resolve_range_falls_back_to_today_for_unknown_period(): void
    {
        [$from, $to] = ReportService::resolveRange('nonsense');

        $this->assertTrue($from->isSameDay(now()));
        $this->assertTrue($to->isSameDay(now()));
    }

    public function test_ranges_do_not_overlap_between_yesterday_and_today(): void
    {
        $this->order(['total_usd' => 30]);
        $this->order(['total_usd' => 70, 'created_at' => now()->subDay()]);

        [$yFrom, $yTo] = ReportService::resolveRange('yesterday');

        $yesterday = ReportService::summary($yFrom, $yTo);
        $today = ReportService::summary(...$this->today());

        $this->assertSame(70.0, $yesterday['salesUsd']);
        $this->assertSame(30.0, $today['salesUsd']);
    }

    // ---------- التفاصيل ----------

    public function test_detailed_rows_are_formatted_and_skip_cancelled(): void
    {
        $product = $this->product('tie-f', 'كرافتة م');

        $this->order([], [['product' => $product, 'qty' => 2, 'unit' => 10, 'cost' => 6]]);
        $this->order(['status' => 'cancelled'], [['product' => $product, 'qty' => 1]]);

        $rows = ReportService::detailed(...$this->today());

        $this->assertCount(1, $rows);
        $this->assertSame('كرافتة م', $rows[0][5]);
        $this->assertEquals(2, $rows[0][7]);
        $this->assertSame('10.00', $rows[0][8]);
        $this->assertSame('مفرق', $rows[0][11]);
    }

    // ---------- الأرباح اليومية (جديد) ----------

    /**
     * الرسم البياني يعرض المبيعات والأرباح معًا، وكانت الأرباح اليومية
     * غير محسوبة أصلًا. وهي تُقرأ من بنود الطلب لا من الطلب.
     */
    public function test_daily_rows_carry_profit_and_item_count(): void
    {
        $product = $this->product('tie-d', 'كرافتة د');

        // بندان: ٢ × (١٠ − ٦) = ٨ ربح
        $this->order(['total_usd' => 20], [
            ['product' => $product, 'qty' => 2, 'unit' => 10, 'cost' => 6],
        ]);

        $rows = ReportService::daily(...$this->today());
        $today = collect($rows)->firstWhere('day', now()->toDateString());

        $this->assertNotNull($today);
        $this->assertSame(2, $today['items']);
        $this->assertEquals(8.0, $today['profit']);
        $this->assertEquals(20.0, $today['sales']);
    }

    /**
     * بند بلا تكلفة معروفة لا يخصم من الربح — وإلا ظهر ربح سالب كاذب.
     */
    public function test_daily_profit_ignores_items_without_a_known_cost(): void
    {
        $product = $this->product('tie-e', 'كرافتة هـ');

        $this->order(['total_usd' => 30], [
            ['product' => $product, 'qty' => 1, 'unit' => 10, 'cost' => 6],
            ['product' => $product, 'qty' => 2, 'unit' => 10, 'cost' => null],
        ]);

        $today = collect(ReportService::daily(...$this->today()))->firstWhere('day', now()->toDateString());

        // ١٠ − ٦ = ٤ فقط، والبند مجهول التكلفة لا يُحسب لا ربحًا ولا خسارة
        $this->assertEquals(4.0, $today['profit']);
        $this->assertEquals(30.0, $today['sales'], 'المبيعات تشمل كل البنود');
    }

    // ---------- المقارنة بالفترة السابقة (جديد) ----------

    public function test_previous_range_has_the_same_length_and_ends_before_the_current_one(): void
    {
        [$from, $to] = [now()->startOfMonth(), now()->endOfMonth()];
        [$pFrom, $pTo] = ReportService::previousRange($from, $to);

        $this->assertTrue($pTo->lt($from), 'الفترة السابقة تنتهي قبل بداية الحالية');
        $this->assertSame(
            $from->diffInDays($to),
            $pFrom->diffInDays($pTo),
            'نفس الطول — وإلا فالمقارنة ظالمة',
        );
    }

    public function test_comparison_reports_the_change_against_the_previous_period(): void
    {
        // الأمس: ١٠٠ · اليوم: ١٥٠ ⇒ +٥٠٪
        $this->order(['total_usd' => 100, 'created_at' => now()->subDay()]);
        $this->order(['total_usd' => 150]);

        [$from, $to] = $this->today();
        $comparison = ReportService::comparison($from, $to, ReportService::summary($from, $to));

        $this->assertEquals(100.0, $comparison['previous']['salesUsd']);
        $this->assertEquals(50.0, $comparison['deltas']['salesUsd']);
    }

    /**
     * حين تكون الفترة السابقة صفرًا لا يوجد أساس للمقارنة. إرجاع «+100%»
     * هنا رقم كاذب؛ والصحيح `null` لتعرض الواجهة شرطة.
     */
    public function test_comparison_returns_null_when_there_is_no_baseline(): void
    {
        $this->order(['total_usd' => 90]);

        [$from, $to] = $this->today();
        $comparison = ReportService::comparison($from, $to, ReportService::summary($from, $to));

        $this->assertSame(0.0, $comparison['previous']['salesUsd']);
        $this->assertNull($comparison['deltas']['salesUsd']);
        $this->assertNull($comparison['deltas']['ordersCount']);
    }

    /** انخفاض المبيعات يجب أن يُقاس بإشارة سالبة لا أن يُقلب */
    public function test_comparison_reports_a_drop_as_negative(): void
    {
        $this->order(['total_usd' => 200, 'created_at' => now()->subDay()]);
        $this->order(['total_usd' => 150]);

        [$from, $to] = $this->today();
        $comparison = ReportService::comparison($from, $to, ReportService::summary($from, $to));

        $this->assertEquals(-25.0, $comparison['deltas']['salesUsd']);
    }
}
