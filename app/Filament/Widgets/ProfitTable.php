<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/** جدول الأرباح التفصيلي — آخر 30 يومًا، مرتب بالربح + هامش ملون + تصدير Excel */
class ProfitTable extends Widget
{
    /**
     * مُستبدَل: جدول الأرباح التفصيلي مكانه صفحة التقارير لا اللوحة — اللوحة
     * تعرض الربح والهامش مجمّلين في MonthlyAccounting. يمكن حذف الملف وview المرافق.
     */
    public static function canView(): bool
    {
        return false;
    }
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static string $view = 'filament.widgets.profit-table';

    public function getRows(): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.name_ar as name,
                SUM(order_items.quantity) as qty,
                SUM(order_items.total_price_usd) as sales,
                SUM(COALESCE(order_items.unit_cost_usd, order_items.unit_price_usd * 0.5) * order_items.quantity) as cost')
            ->groupBy('order_items.name_ar')
            ->get();

        return $rows->map(function ($r) {
            $sales = (float) $r->sales;
            $cost = (float) $r->cost;
            $profit = $sales - $cost;
            $margin = $sales > 0 ? round($profit / $sales * 100) : 0;

            return [
                'name' => $r->name,
                'qty' => (int) $r->qty,
                'sales' => $sales,
                'cost' => $cost,
                'profit' => $profit,
                'margin' => $margin,
            ];
        })
            ->filter(fn ($r) => $r['qty'] > 0)
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    public function getTotals(): array
    {
        $rows = $this->getRows();

        return [
            'sales' => collect($rows)->sum('sales'),
            'cost' => collect($rows)->sum('cost'),
            'profit' => collect($rows)->sum('profit'),
        ];
    }

    /** تصدير Excel عبر التقرير الموجود */
    public function exportExcel()
    {
        return redirect()->route('admin.reports.export', [
            'period' => 'last30',
            'type' => 'xlsx',
        ]);
    }

    public function render(): View
    {
        return view('filament.widgets.profit-table', [
            'rows' => $this->getRows(),
            'totals' => $this->getTotals(),
        ]);
    }
}
