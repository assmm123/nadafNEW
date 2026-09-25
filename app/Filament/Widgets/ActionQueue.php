<?php

namespace App\Filament\Widgets;

use App\Models\AbandonedCart;
use App\Models\ChatLog;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Filament\Resources\ChatLogResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\StockMovementResource;
use Filament\Widgets\Widget;

/**
 * الطبقة الأولى في اللوحة: «يحتاج إجراءك الآن».
 *
 * كل بطاقة رقم قابل للنقر يفتح القائمة المعنية. والبطاقة التي رقمها صفر
 * تظهر باهتة لا مخفية — فيخفّ الضجيج البصري ولا يفقد المالك إحساس الاكتمال.
 *
 * والبطاقات تتبع الدور: مسؤول المخزون لا يرى بطاقات الطلبات، والدعم لا يرى
 * بطاقات المخزون. فتصير اللوحة مكتب عمل لكل دور لا صفحة واحدة للجميع.
 */
class ActionQueue extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.action-queue';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasPermission('orders.view')
            || (bool) $user?->hasPermission('stock.view')
            || (bool) $user?->hasPermission('chat.view');
    }

    /** @return array<int, array<string, mixed>> */
    public function getCards(): array
    {
        $user = auth()->user();
        $cards = [];

        if ($user?->hasPermission('orders.view')) {
            $cards[] = [
                'count' => Order::where('status', 'pending')->count(),
                'label' => 'طلبات بانتظار التأكيد',
                'tone' => 'warn',
                'url' => OrderResource::getUrl('index'),
            ];

            $cards[] = [
                'count' => Order::whereNotNull('payment_proof_path')
                    ->whereNull('payment_confirmed_at')
                    ->whereNotIn('status', ['cancelled'])
                    ->count(),
                'label' => 'إثباتات دفع بانتظار الاعتماد',
                'tone' => 'brass',
                'url' => OrderResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('stock.view')) {
            $cards[] = [
                'count' => ProductVariant::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
                'label' => 'نواقص مخزون',
                'tone' => 'danger',
                'url' => StockMovementResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('purchasing.view')) {
            $cards[] = [
                'count' => \App\Models\PurchaseInvoice::where('status', 'draft')->count(),
                'label' => 'فواتير شراء مسودة',
                'tone' => 'brass',
                'url' => PurchaseInvoiceResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('chat.view')) {
            $cards[] = [
                'count' => ChatLog::whereNull('matched_answer')->count(),
                'label' => 'أسئلة دردشة بلا جواب',
                'tone' => 'info',
                'url' => ChatLogResource::getUrl('index'),
            ];
        }

        if ($user?->hasPermission('orders.view')) {
            $cards[] = [
                'count' => AbandonedCart::where('notified', false)->count(),
                'label' => 'سلات متروكة للمتابعة',
                'tone' => 'muted',
                'url' => null,
            ];
        }

        return $cards;
    }
}
