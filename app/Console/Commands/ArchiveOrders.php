<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * الأرشفة التلقائية للطلبات — تعمل يوميًا 2 فجرًا عبر الجدولة.
 * الطلبات المنتهية (delivered / cancelled) الأقدم من العدد المحدد من الأيام تُؤرشف.
 */
class ArchiveOrders extends Command
{
    protected $signature = 'orders:archive';

    protected $description = 'أرشفة الطلبات المنتهية الأقدم من المدة المحددة تلقائيًا';

    public function handle(): int
    {
        if (! Setting::bool('archiving_enabled', true)) {
            $this->info('الأرشفة التلقائية معطلة من الإعدادات');

            return self::SUCCESS;
        }

        $days = max(1, (int) Setting::get('archive_after_days', 30));
        $cutoff = now()->subDays($days);

        // 1) المكتملة المختومة (ذهبي) تبقى ظاهرة 34 ساعة بعد التسليم ثم تُؤرشف تلقائيًا
        $stampedCount = Order::where('status', 'delivered')
            ->whereNull('archived_at')
            ->whereNotNull('stamped_at')
            ->where('stamped_at', '<', now()->subHours(34))
            ->update(['archived_at' => now()]);

        // 2) الملغاة والمكتملة القديمة أقدم من المدة المحددة
        $count = Order::whereIn('status', ['delivered', 'cancelled'])
            ->whereNull('archived_at')
            ->where('created_at', '<', $cutoff)
            ->update(['archived_at' => now()]);

        $this->info("{$stampedCount} طلبًا مختومًا أُرشف (بعد 34 ساعة من التسليم)، {$count} طلبًا قديمًا أُرشف (أقدم من {$days} يوم)");

        return self::SUCCESS;
    }
}
