<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * تنظيف ملفات إثبات الدفع اليتيمة.
 *
 * المشكلة: يُرفع الإيصال في CheckoutForm قبل إنشاء الطلب، فإن فشل الإنشاء
 * (نفاد مخزون لحظي، كوبون غير صالح، استثناء ...) يبقى الملف على القرص بلا
 * أي طلب يرتبط به — تراكم صامت لا ينتهي.
 *
 * هذا الأمر يمسح كل ملف في مجلد payment-proofs لا يُشير إليه أي طلب،
 * بشرط أن يكون أقدم من عدد الساعات المحدد (مهلة أمان تمنع حذف ملف ما زال
 * قيد المعاملة).
 */
class PruneOrphanProofs extends Command
{
    protected $signature = 'orders:prune-proofs
                            {--hours=24 : عمر الملف بالساعات قبل اعتباره يتيمًا}
                            {--dry-run : عرض ما سيُحذف دون حذف فعلي}';

    protected $description = 'حذف ملفات إثبات الدفع اليتيمة (غير المرتبطة بأي طلب)';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $directory = 'payment-proofs';
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours)->getTimestamp();

        if (! $disk->exists($directory)) {
            $this->info('مجلد payment-proofs غير موجود — لا شيء للحذف');

            return self::SUCCESS;
        }

        $files = $disk->files($directory);

        if ($files === []) {
            $this->info('لا ملفات في payment-proofs');

            return self::SUCCESS;
        }

        // المسارات المستخدمة فعلًا في الطلبات — أي ملف خارج هذه المجموعة يتيم
        $referenced = Order::query()
            ->whereNotNull('payment_proof_path')
            ->pluck('payment_proof_path')
            ->filter()
            ->map(fn (string $path) => ltrim($path, '/'))
            ->flip()
            ->all();

        $deleted = 0;
        $bytes = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (isset($referenced[ltrim($file, '/')])) {
                continue;
            }

            // مهلة الأمان: لا نلمس الملفات الحديثة (قد تكون قيد معاملة جارية)
            if ($disk->lastModified($file) > $cutoff) {
                $skipped++;

                continue;
            }

            $bytes += (int) $disk->size($file);

            if (! $dryRun) {
                $disk->delete($file);
            }

            $deleted++;
            $this->line(($dryRun ? '[تجربة] سيُحذف: ' : 'حُذف: ').$file);
        }

        $size = round($bytes / 1024, 1);
        $mode = $dryRun ? '(تجربة بدون حذف)' : '';

        $this->info("{$deleted} ملف يتيم {$mode} — {$size} كيلوبايت. تم تخطي {$skipped} ملف حديث (أحدث من {$hours} ساعة).");

        return self::SUCCESS;
    }
}
