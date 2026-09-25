<?php

namespace App\Filament\Widgets;

use App\Models\AbandonedCart;
use App\Models\Order;
use App\Models\ProductVariant;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

/**
 * تنبيه البضاعة + سلات متروكة + آخر الطلبيات مع زر واتساب + شريط السلامة
 */
class AlertsWidget extends Widget
{
    /**
     * مُستبدَل بـActionQueue: نواقص المخزون والسلات المتروكة صارتا بطاقتين
     * قابلتين للنقر. يمكن حذف الملف وview المرافق.
     */
    public static function canView(): bool
    {
        return false;
    }
    protected static string $view = 'filament.widgets.alerts';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    /** الكمية العالمية (20 وحدة دولية ببعضها) يفترض بها مبيعات عبر الفحص */
    public int $unitTestCount = 20;

    public function getLowStock(): array
    {
        return ProductVariant::with('product')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orderBy('quantity')
            ->take(5)
            ->get()
            ->map(fn ($v) => [
                'name' => $v->product->name_ar.($v->label() ? " ({$v->label()})" : ''),
                'qty' => $v->quantity,
                'out' => $v->quantity <= 0,
            ])
            ->all();
    }

    public function getAbandoned(): array
    {
        return AbandonedCart::where('notified', false)
            ->where('total_usd', '>', 0)
            ->where('last_activity_at', '>=', now()->subDays(3))
            ->orderByDesc('total_usd')
            ->take(3)
            ->get()
            ->map(fn ($c) => [
                'total' => '$'.number_format((float) $c->total_usd, 2),
                'ago' => $c->last_activity_at?->diffForHumans(),
                'items' => collect($c->items ?? [])->take(2)->pluck('name')->implode('، '),
            ])
            ->all();
    }

    public function getRecentOrders(): array
    {
        return Order::with(['user', 'paymentMethod'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn ($o) => [
                'code' => $o->order_code,
                'customer' => $o->user->name,
                'total' => fmt_usd($o->total_usd),
                'status' => \App\Models\Order::statusLabel($o->status),
                'statusColor' => $o->status === 'pending' ? 'nad-chip-wr' : ($o->status === 'delivered' ? 'nad-chip-ok' : 'nad-chip-in'),
                'phone' => preg_replace('/\D/', '', $o->user->phone ?? ''),
                'waText' => rawurlencode("مرحبًا {$o->user->name} 👋\nبخصوص طلبك {$o->order_code} من متجر نداف — الإجمالي: ".fmt_usd($o->total_usd)."\nهل تحتاج أي مساعدة؟"),
                'viewUrl' => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $o]),
            ])
            ->all();
    }

    /** شريط السلامة السفلي */
    public function getHealth(): array
    {
        $logPath = storage_path('logs/laravel.log');
        $errorsToday = 0;
        if (is_file($logPath)) {
            $tail = substr(file_get_contents($logPath), -50000);
            $today = date('Y-m-d');
            preg_match_all('/\['.preg_quote($today).' [0-9:]{8}\] local\.ERROR/', $tail, $m);
            $errorsToday = count($m[0]);
        }
        $lastBackup = is_file(storage_path('app/backup-info.json'))
            ? json_decode(file_get_contents(storage_path('app/backup-info.json')), true)['date'] ?? 'غير مسجل'
            : 'غير مسجل — فعّل النسخ اليومي';
        return [
            'backup' => 'آخر نسخة احتياطية: '.$lastBackup,
            'errors' => $errorsToday,
            'scheduler' => is_file(storage_path('framework/schedule-'.date('Ymd')))? 'حي ✓': 'غير مؤكد (يعمل فقط أثناء تشغيل serve)',
        ];
    }

    public function render(): View
    {
        return view('filament.widgets.alerts', [
            'lowStock' => $this->getLowStock(),
            'abandoned' => $this->getAbandoned(),
            'recentOrders' => $this->getRecentOrders(),
            'health' => $this->getHealth(),
        ]);
    }
}
