<?php

namespace App\Support;

use App\Filament\Resources\ChatLogResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Models\AbandonedCart;
use App\Models\ChatLog;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Services\ReportService;
use Illuminate\Support\Collection;

/**
 * بيانات لوحة التحكم — مطابقة لنموذج NADAF-Dashboard-Concept.html بالحرف.
 *
 * الصفحة عرضٌ فقط: لا استعلام واحد فيها. والأرباح تُقرأ من ReportService
 * نفسه الذي يغذّي صفحة التقارير والتصدير، فلا يوجد رقمان لنفس الشيء.
 */
final class DashboardData
{
    /**
     * بطاقات «يحتاج إجراءك الآن» — بنفس ترتيب النموذج ونصوصه.
     * التصفية بالصلاحية قد تحذف بطاقة، ولا تضيف ولا تعيد ترتيبًا.
     */
    public static function actions(): array
    {
        $user = auth()->user();
        $cards = [];

        if ($user?->hasPermission('orders.view')) {
            $cards[] = [
                'value' => Order::where('status', 'pending')->count(),
                'label' => 'طلبات بانتظار التأكيد',
                'hint' => 'تحتاج مراجعة وإثبات دفع',
                'color' => '#E8B98A',
                'url' => OrderResource::getUrl('index'),
            ];

            $cards[] = [
                'value' => Order::whereNotNull('payment_proof_path')
                    ->whereNull('payment_confirmed_at')
                    ->whereNotIn('status', ['cancelled'])
                    ->count(),
                'label' => 'إثباتات دفع بانتظار الاعتماد',
                'hint' => 'اضغط «مقبوض» لإصدار الفاتورة',
                'color' => '#EAC97F',
                'url' => OrderResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('stock.view')) {
            $cards[] = [
                'value' => ProductVariant::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
                'label' => 'نواقص مخزون',
                'hint' => 'وصلت حد التنبيه أو نفدت',
                'color' => '#E9A3B2',
                'url' => StockMovementResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('chat.view')) {
            $cards[] = [
                'value' => ChatLog::whereNull('matched_answer')->count(),
                'label' => 'أسئلة دردشة بلا جواب',
                'hint' => 'أجب مرة فتُجاب تلقائيًا بعدها',
                'color' => '#9DC0DC',
                'url' => ChatLogResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('orders.view')) {
            $cards[] = [
                'value' => AbandonedCart::where('notified', false)->count(),
                'label' => 'سلات متروكة',
                'hint' => 'متروكة قبل إتمام الشراء',
                'color' => '#F1ECE1',
                'url' => null,
            ];
        }

        return $cards;
    }

    /** «المحاسبة — هذا الشهر»: قائمة الدخل المبسّطة + سلسلة الرسم البياني */
    public static function accounting(): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $summary = ReportService::summary($from, $to);

        $gross = (float) $summary['salesUsd'];
        $profit = (float) $summary['profitUsd'];
        $cogs = round($gross - $profit, 2);

        $ordersCount = (int) $summary['ordersCount'];

        // الخصومات والشحن المحصّل — لا يعطيهما الملخص، فتُقرأ مباشرة
        $discounts = (float) Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->sum('discount_usd');

        $shipping = (float) Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->sum('shipping_usd');

        $netRevenue = round($gross - $discounts + $shipping, 2);
        $margin = $netRevenue > 0 ? (int) round($profit / $netRevenue * 100) : 0;
        $avgOrder = $ordersCount > 0 ? round($gross / $ordersCount, 2) : 0.0;

        return [
            'period' => $from->format('d/m/Y').' — '.$to->format('d/m/Y'),
            'gross' => $gross,
            'discounts' => $discounts,
            'shipping' => $shipping,
            'netRevenue' => $netRevenue,
            'cogs' => $cogs,
            'profit' => $profit,
            'margin' => $margin,
            'orders' => $ordersCount,
            'avgOrder' => $avgOrder,
            'items' => (int) $summary['itemsSold'],
            'chart' => self::chart(),
        ];
    }

    /**
     * سلسلة ١٤ يومًا **متصلة** (الأيام بلا مبيعات = صفر) — النموذج يعرض
     * أعمدة متتابعة لا أعمدة متفرّقة، فالتجميع بالقفز ينتج رسمًا مضلّلًا.
     */
    private static function chart(): array
    {
        $rows = collect(ReportService::daily(now()->subDays(13)->startOfDay(), now()->endOfDay()))
            ->keyBy('day');

        $series = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i);

            $series[] = [
                'day' => $day->toDateString(),
                'label' => $day->format('d/m'),
                'sales' => (float) ($rows[$day->toDateString()]['sales'] ?? 0),
            ];
        }

        $peak = max(array_column($series, 'sales'));
        $average = round(array_sum(array_column($series, 'sales')) / count($series), 2);

        // محور بخطوات مستديرة: أربع فترات بخطوة من مضاعفات ١٠٠
        $step = max(100, (int) (ceil(max($peak, 1) / 4 / 100) * 100));
        $axisMax = $step * 4;

        return [
            'bars' => array_map(fn ($d) => [
                'label' => $d['label'],
                'sales' => $d['sales'],
                'ratio' => min(1, $d['sales'] / $axisMax),
            ], $series),
            'averageRatio' => min(1, $average / $axisMax),
            'axis' => [$axisMax, $step * 3, $step * 2, $step, 0],
            'step' => $step,
        ];
    }

    /**
     * «المركز المالي — اليوم»: عمودا الأصول والالتزامات — **بالليرة السورية**
     * كما في النموذج. الطلبات تُجمع من total_syp المحفوظ بسعر صرفها لحظتها،
     * والمخزون وفواتير الشراء (مسعّرة بالدولار) تُحوَّل بسعر الصرف الحالي.
     */
    public static function position(): array
    {
        $rate = current_exchange_rate();

        $cash = (float) Order::whereDate('payment_confirmed_at', today())
            ->where('status', '!=', 'cancelled')
            ->sum('total_syp');

        $receivable = (float) Order::whereNull('payment_confirmed_at')
            ->whereNotIn('status', ['cancelled', 'delivered'])
            ->sum('total_syp');

        // نفس معيار بقية اللوحة: التكلفة الفعلية، وإن غابت فنصف سعر البيع
        $inventoryUsd = (float) ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->selectRaw('SUM(product_variants.quantity * COALESCE(products.cost_usd, products.price_usd * 0.5)) as v')
            ->value('v');

        $inventory = round($inventoryUsd * $rate);

        $payable = round((float) PurchaseInvoice::where('status', 'confirmed')->sum('total_cost') * $rate);

        return [
            'rate' => $rate,
            'cash' => $cash,
            'receivable' => $receivable,
            'inventory' => $inventory,
            'assetsTotal' => $cash + $receivable + $inventory,
            'payable' => $payable,
            'liabilitiesTotal' => $payable,
            'net' => $cash + $receivable - $payable,
        ];
    }

    /** «أحدث الطلبات» — الأعمدة السبعة كما في النموذج */
    public static function latestOrders(int $limit = 6): Collection
    {
        return Order::with('user')->latest()->take($limit)->get();
    }
}
