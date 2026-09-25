<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseInvoice;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * الطبقة الثالثة: «المركز المالي — اليوم».
 *
 * الفرق بينها وبين المحاسبة: المحاسبة تقيس الأداء (بعت كم وربحت كم)،
 * والمركز المالي يقيس **أين المال الآن**: بيدك، أم لك عند الناس، أم عليك
 * للموردين، أم مجمّد في المخزون. أربعة أرقام معًا هي ما يجعل اللوحة محاسبية
 * لا إحصائية — لوحة تعرض «المبيعات» وحدها لا تقول إن 380 ألفًا لم تُقبض بعد.
 */
class FinancialPosition extends BaseWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('reports.view');
    }

    protected function getStats(): array
    {
        $cashToday = (float) \App\Models\Order::whereDate('payment_confirmed_at', today())
            ->where('status', '!=', 'cancelled')
            ->sum('total_usd');

        $receivable = (float) \App\Models\Order::whereNull('payment_confirmed_at')
            ->whereNotIn('status', ['cancelled', 'delivered'])
            ->sum('total_usd');

        $payable = (float) PurchaseInvoice::where('status', 'confirmed')->sum('total_cost');

        // نفس معيار بقية اللوحة: التكلفة الفعلية، وإن غابت فنصف سعر البيع
        $inventory = (float) ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->selectRaw('SUM(product_variants.quantity * COALESCE(products.cost_usd, products.price_usd * 0.5)) as v')
            ->value('v');

        $net = $cashToday + $receivable - $payable;

        return [
            Stat::make('مقبوض اليوم', '$'.number_format($cashToday, 2))
                ->description('المال الذي دخل فعلًا اليوم')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('ذمم مدينة — لك', '$'.number_format($receivable, 2))
                ->description('طلبات لم تُقبض بعد')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('ذمم دائنة — عليك', '$'.number_format($payable, 2))
                ->description('فواتير شراء مؤكدة لم تُسدَّد')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make('قيمة المخزون', '$'.number_format($inventory, 2))
                ->description('رأس مال مجمّد في البضاعة')
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray'),

            Stat::make('صافي المركز النقدي', '$'.number_format($net, 2))
                ->description('مقبوض + ذمم مدينة − ذمم دائنة')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($net >= 0 ? 'success' : 'danger'),
        ];
    }
}
