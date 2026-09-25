<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** مبيعات وأرباح آخر 30 يومًا — عمودان لكل يوم */
class OrdersChart extends ChartWidget
{
    /**
     * مُستبدَل: مخطط المبيعات اليومية انتقل داخل MonthlyAccounting.
     * يبقى في الكود للمراجعة فقط — يمكن حذف الملف وview المرافق.
     */
    public static function canView(): bool
    {
        return false;
    }
    protected static ?string $heading = 'المبيعات والأرباح — آخر 30 يومًا ($)';
    protected static ?string $maxHeight = '260px';
    protected static ?int $sort = 2;

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }

    protected function getData(): array
    {
        $rows = Order::where('created_at', '>=', now()->subDays(30))
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as day, SUM(total_usd) as sales')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $profits = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('DATE(orders.created_at) as day, SUM((order_items.unit_price_usd - COALESCE(order_items.unit_cost_usd, order_items.unit_price_usd * 0.5)) * order_items.quantity) as profit')
            ->groupBy('day')
            ->pluck('profit', 'day');

        // 30 يومًا متواصلة (الأيام الفارغة = 0)
        $days = collect()->range(0, 29)->map(fn ($i) => now()->subDays(29 - $i)->format('Y-m-d'));

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات ($)',
                    'data' => $days->map(fn ($d) => round((float) ($rows[$d]->sales ?? 0), 2))->all(),
                    'backgroundColor' => 'rgba(210, 162, 78, 0.75)',
                    'borderColor' => '#D2A24E',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'الأرباح ($)',
                    'data' => $days->map(fn ($d) => round((float) ($profits[$d] ?? 0), 2))->all(),
                    'backgroundColor' => 'rgba(78, 165, 124, 0.7)',
                    'borderColor' => '#4EA57C',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $days->map(fn ($d) => substr($d, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
