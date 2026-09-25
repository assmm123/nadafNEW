<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** أرقام اليوم بالمقارنة مع أمس — لا إجماليات متجمدة */
class StatsOverview extends BaseWidget
{
    /**
     * مُستبدَل: أرقامه توزّعت على MonthlyAccounting (المبيعات والربح والهامش)
     * وFinancialPosition (المركز المالي). يبقى في الكود للمراجعة فقط — يمكن
     * حذف هذا الملف وview المرافق له بأمان.
     */
    public static function canView(): bool
    {
        return false;
    }
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $salesToday = (float) Order::whereDate('created_at', today())
            ->where('status', '!=', 'cancelled')->sum('total_usd');
        $salesYesterday = (float) Order::whereDate('created_at', today()->subDay())
            ->where('status', '!=', 'cancelled')->sum('total_usd');

        $ordersToday = Order::whereDate('created_at', today())->count();
        $ordersYesterday = Order::whereDate('created_at', today()->subDay())->count();

        $usersToday = User::whereDate('created_at', today())->count();
        $usersYesterday = User::whereDate('created_at', today()->subDay())->count();

        // أرباح اليوم المقدّرة (بيع - تكلفة الشراء الفعلية لكل منتج)
        $profitToday = $this->profitFor(today());
        $profitYesterday = $this->profitFor(today()->subDay());

        return [
            $this->statCompare('مبيعات اليوم', '$'.number_format($salesToday, 2), $salesToday, $salesYesterday),
            $this->statCompare('طلبيات اليوم', (string) $ordersToday, $ordersToday, $ordersYesterday),
            $this->statCompare('أرباح اليوم (تقديري)', '$'.number_format($profitToday, 2), $profitToday, $profitYesterday),
            $this->statCompare('عملاء جدد اليوم', (string) $usersToday, $usersToday, $usersYesterday),
        ];
    }

    /** أرباح يوم محدد: (سعر البيع - التكلفة) × الكمية لكل عنصر غير ملغى */
    private function profitFor($date): float
    {
        return (float) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.created_at', $date)
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('SUM((order_items.unit_price_usd - COALESCE(order_items.unit_cost_usd, order_items.unit_price_usd * 0.5)) * order_items.quantity) as p')
            ->value('p');
    }

    /** صندوق مع سهم مقارنة بالأمس */
    private function statCompare(string $label, string $value, float $today, float $yesterday): Stat
    {
        $diff = $today - $yesterday;
        $icon = $diff > 0 ? 'heroicon-m-arrow-trending-up' : ($diff < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus');
        $trend = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'gray');

        $desc = $yesterday > 0
            ? number_format(abs($diff / $yesterday * 100), 0).'% '.($diff > 0 ? 'أفضل من' : 'أقل من').' أمس ($'.number_format($yesterday, 2).')'
            : ($today > 0 ? 'أمس: لا مبيعات' : 'مطابق لأمس');

        return Stat::make($label, $value)
            ->description($desc)
            ->descriptionIcon($icon)
            ->color($today > 0 ? $trend : 'gray');
    }
}
