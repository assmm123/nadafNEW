<?php

namespace App\Console\Commands;

use App\Models\AbandonedCart;
use App\Models\Setting;
use App\Services\ReportService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * الأتمتة المجدولة للبوتات:
 * 1) استقبال أوامر البوت التفاعلي (كل دقيقة)
 * 2) تقرير صباحي 9:00 ومسائي 21:00
 * 3) تنبيه السلات المتروكة مرتان يوميًا
 */
class BotAutomation extends Command
{
    protected $signature = 'bot:automation {task=poll : poll | morning | evening | abandoned}';

    protected $description = 'أتمتة البوتات: استقبال الأوامر، التقارير المجدولة، تنبيهات السلات المتروكة';

    public function handle(): int
    {
        $task = $this->argument('task');

        switch ($task) {
            case 'poll':
                $count = \App\Services\TelegramBotService::poll();
                $this->info("poll: {$count} رسالة جديدة");

                break;

            case 'morning':
                $this->sendScheduledReport('صباحي');

                break;

            case 'evening':
                $this->sendScheduledReport('مسائي');

                break;

            case 'abandoned':
                $this->notifyAbandonedCarts();

                break;

            default:
                $this->error("مهمة غير معروفة: {$task}");

                return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sendScheduledReport(string $label): void
    {
        if (! Setting::bool('bot_scheduled_reports')) {
            return;
        }

        [$from, $to, $range] = $label === 'صباحي'
            ? [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'أمس']
            : [now()->startOfDay(), now(), 'اليوم حتى الآن'];

        $summary = ReportService::summary($from, $to);
        $lowCount = \App\Models\ProductVariant::whereColumn('quantity', '<=', 'low_stock_threshold')
            ->where('quantity', '>', 0)->count();
        $outCount = \App\Models\ProductVariant::where('quantity', '<=', 0)->count();

        $text = "🌅 تقرير {$range} — متجر نداف\n\n".
            "• الطلبات: {$summary['ordersCount']}\n".
            '• المبيعات: '.fmt_usd($summary['salesUsd'])."\n".
            '• بالليرة: '.number_format($summary['salesSyp'])." ل.س\n".
            '• الأرباح المقدّرة: '.fmt_usd($summary['profitUsd'])."\n\n".
            "📦 المخزون: {$lowCount} منخفض، {$outCount} نافد\n\n";

        // اقتراح شراء إن كان هناك نواقص
        if ($outCount > 0 || $lowCount > 0) {
            $text .= "💡 اقتراح: راجع الأصناف النافدة والمنخفضة وأنشئ فاتورة شراء لتجديدها — أرسل «منخفض» للبوت التفاعلي لعرض القائمة.";
        }

        TelegramService::sendInventory($text);
        $this->info("تقرير {$label} أُرسل");
    }

    private function notifyAbandonedCarts(): void
    {
        if (! Setting::bool('bot_abandoned_alerts')) {
            return;
        }

        // سلات نشطة خلال آخر 48 ساعة، غير مبلّغ عنها، توقفت عن الحركة 3+ ساعات
        $carts = AbandonedCart::where('notified', false)
            ->where('total_usd', '>', 0)
            ->where('last_activity_at', '<', now()->subHours(3))
            ->where('last_activity_at', '>', now()->subHours(48))
            ->orderByDesc('total_usd')
            ->take(10)
            ->get();

        if ($carts->isEmpty()) {
            $this->info('لا سلات متروكة جديدة');

            return;
        }

        $total = $carts->sum('total_usd');
        $lines = ["🛒 سلات متروكة ({$carts->count()}) بقيمة ".fmt_usd((float) $total).":\n"];

        foreach ($carts as $cart) {
            $items = collect($cart->items ?? [])->take(2)->pluck('name')->implode('، ');
            $who = $cart->identifier ?: ($cart->user?->name ?: 'زائر بدون بيانات');
            $lines[] = "• {$who} — ".fmt_usd((float) $cart->total_usd)." — {$items}";
            $cart->update(['notified' => true]);
        }

        $lines[] = "\n💡 تواصل معهم عبر واتساب لاستكمال الشراء (خصم صغير قد يعيد العميل).";

        TelegramService::sendInventory(implode("\n", $lines));
        $this->info("أُرسل تنبيه {$carts->count()} سلة متروكة");
    }
}
