<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** المدى الزمني حسب اختيار الفترة */
    public static function resolveRange(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'اليوم'],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'أمس'],
            'last7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'آخر 7 أيام'],
            'last30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'آخر 30 يومًا'],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'هذا الأسبوع'],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek(), 'الأسبوع الماضي'],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'هذا الشهر'],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth(), 'الشهر الماضي'],
            'custom' => [
                Carbon::parse($from ?: $now->toDateString())->startOfDay(),
                Carbon::parse($to ?: $now->toDateString())->endOfDay(),
                'من '.($from ?: $now->toDateString()).' إلى '.($to ?: $now->toDateString()),
            ],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'اليوم'],
        };
    }

    /** الملخص الكامل للفترة */
    public static function summary(Carbon $from, Carbon $to): array
    {
        $range = [$from, $to];

        $ordersBase = Order::whereBetween('created_at', $range)->where('status', '!=', 'cancelled');

        $ordersCount = (clone $ordersBase)->count();
        $salesUsd = round((clone $ordersBase)->sum('total_usd'), 2);
        $salesSyp = round((clone $ordersBase)->sum('total_syp'), 0);
        $avgUsd = $ordersCount ? round($salesUsd / $ordersCount, 2) : 0.0;
        $stampedCount = (clone $ordersBase)->whereNotNull('stamped_at')->count();

        // عناصر البيع والأرباح (الأرباح تُحسب للأصناف المعروفة التكلفة فقط)
        $itemsBase = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', $range)
            ->where('orders.status', '!=', 'cancelled');

        $itemsSold = (int) (clone $itemsBase)->sum('order_items.quantity');
        $itemsSalesUsd = round((clone $itemsBase)->sum('order_items.total_price_usd'), 2);
        $profitUsd = round((float) (clone $itemsBase)
            ->whereNotNull('order_items.unit_cost_usd')
            ->selectRaw('COALESCE(SUM(order_items.total_price_usd - order_items.unit_cost_usd * order_items.quantity), 0) as p')
            ->value('p'), 2);
        $itemsWithCost = (int) (clone $itemsBase)->whereNotNull('order_items.unit_cost_usd')->count();

        return compact('ordersCount', 'salesUsd', 'salesSyp', 'avgUsd', 'stampedCount', 'itemsSold', 'itemsSalesUsd', 'profitUsd', 'itemsWithCost');
    }

    /** المبيعات حسب القسم */
    public static function byCategory(Carbon $from, Carbon $to): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('categories.id', 'categories.name_ar')
            ->orderByDesc('sales')
            ->get([
                'categories.name_ar as name',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('ROUND(SUM(order_items.total_price_usd), 2) as sales'),
                DB::raw('ROUND(COALESCE(SUM(CASE WHEN order_items.unit_cost_usd IS NOT NULL THEN order_items.total_price_usd - order_items.unit_cost_usd * order_items.quantity ELSE 0 END), 0), 2) as profit'),
            ])
            ->toArray();
    }

    /** أفضل المنتجات */
    public static function byProduct(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('products.id', 'products.name_ar')
            ->orderByDesc('sales')
            ->limit($limit)
            ->get([
                'products.name_ar as name',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('ROUND(SUM(order_items.total_price_usd), 2) as sales'),
                DB::raw('ROUND(COALESCE(SUM(CASE WHEN order_items.unit_cost_usd IS NOT NULL THEN order_items.total_price_usd - order_items.unit_cost_usd * order_items.quantity ELSE 0 END), 0), 2) as profit'),
            ])
            ->toArray();
    }

    /**
     * السجل اليومي — مبيعات وأرباح وبنود لكل يوم.
     *
     * الأرباح تُقرأ من بنود الطلب لا من الطلب نفسه (الطلب لا يحمل تكلفة)،
     * فتُجمع في استعلام ثانٍ وتُدمج في صفوف الأيام. والدمج عمدًا بدل ضمّ
     * الجدولين: LEFT JOIN بين orders وorder_items **يضاعف** مجموع total_usd
     * بعدد بنود كل طلب، فيتضخّم رقم المبيعات بصمت.
     */
    public static function daily(Carbon $from, Carbon $to): array
    {
        $days = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->groupBy('day')
            ->orderByDesc('day')
            ->get([
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('ROUND(SUM(total_usd), 2) as sales'),
                DB::raw('ROUND(SUM(total_syp), 0) as sales_syp'),
            ])
            ->keyBy('day');

        $extras = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('day')
            ->get([
                DB::raw('DATE(orders.created_at) as day'),
                DB::raw('SUM(order_items.quantity) as items'),
                DB::raw('ROUND(COALESCE(SUM(CASE WHEN order_items.unit_cost_usd IS NOT NULL THEN order_items.total_price_usd - order_items.unit_cost_usd * order_items.quantity ELSE 0 END), 0), 2) as profit'),
            ])
            ->keyBy('day');

        return $days->map(fn ($row) => [
            'day' => $row->day,
            'orders' => (int) $row->orders,
            'sales' => (float) $row->sales,
            'sales_syp' => (float) $row->sales_syp,
            'items' => (int) ($extras[$row->day]->items ?? 0),
            'profit' => (float) ($extras[$row->day]->profit ?? 0),
        ])->values()->toArray();
    }

    /**
     * الفترة السابقة المكافئة — بنفس الطول، تنتهي قبل بداية الفترة الحالية.
     * منها تُحسب نسبة التغيّر، فصار الرقم يقول «هل هذا أفضل أم أسوأ» لا قيمته وحدها.
     */
    public static function previousRange(Carbon $from, Carbon $to): array
    {
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        return [
            $from->copy()->subDays($days)->startOfDay(),
            $from->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * مقارنة الفترة الحالية بالسابقة.
     *
     * `$current` يُمرَّر من الخارج لأن الصفحة حسبته أصلًا — وإعادة حسابه هنا
     * تعني سبعة استعلامات إضافية في كل تحميل بلا فائدة.
     *
     * القيمة `null` في النسب تعني «لا أساس للمقارنة» (الفترة السابقة صفر)،
     * وتُعرض في الواجهة شرطةً لا «+100%» كاذبة.
     */
    public static function comparison(Carbon $from, Carbon $to, array $current): array
    {
        [$pFrom, $pTo] = self::previousRange($from, $to);
        $previous = self::summary($pFrom, $pTo);

        $delta = function (float|int $now, float|int $before): ?float {
            if ((float) $before === 0.0) {
                return (float) $now === 0.0 ? 0.0 : null;
            }

            return round((($now - $before) / abs($before)) * 100, 1);
        };

        return [
            'from' => $pFrom,
            'to' => $pTo,
            'previous' => $previous,
            'deltas' => [
                'ordersCount' => $delta($current['ordersCount'], $previous['ordersCount']),
                'salesUsd' => $delta($current['salesUsd'], $previous['salesUsd']),
                'profitUsd' => $delta($current['profitUsd'], $previous['profitUsd']),
                'avgUsd' => $delta($current['avgUsd'], $previous['avgUsd']),
                'itemsSold' => $delta($current['itemsSold'], $previous['itemsSold']),
            ],
        ];
    }

    /** التفاصيل سطرًا بسطر (للتصدير) */
    public static function detailed(Carbon $from, Carbon $to): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->orderBy('orders.created_at')
            ->get([
                'orders.created_at',
                'orders.order_code',
                'orders.status',
                'users.name as customer',
                'categories.name_ar as category',
                'order_items.name_ar as product',
                'order_items.color',
                'order_items.size',
                'order_items.quantity',
                'order_items.unit_price_usd',
                'order_items.unit_cost_usd',
                'order_items.total_price_usd',
                'order_items.is_wholesale',
            ])
            ->map(fn ($r) => [
                $r->created_at->format('Y/m/d H:i'),
                $r->order_code,
                Order::statusLabel($r->status),
                $r->customer,
                $r->category ?? '-',
                $r->product,
                trim(($r->color ?? '').' '.($r->size ?? '')) ?: '-',
                $r->quantity,
                number_format((float) $r->unit_price_usd, 2, '.', ''),
                $r->unit_cost_usd !== null ? number_format((float) $r->unit_cost_usd, 2, '.', '') : '-',
                number_format((float) $r->total_price_usd, 2, '.', ''),
                $r->is_wholesale ? 'جملة' : 'مفرق',
            ])
            ->toArray();
    }
}
