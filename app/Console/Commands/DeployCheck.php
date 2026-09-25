<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * فحص جاهزية النشر — يُشغَّل قبل فتح المتجر للجمهور.
 *
 * الغرض: أخطاء الإعداد لا تظهر في الاختبارات. المتجر يعمل محليًا بـ
 * `APP_DEBUG=true` وبريد «log» لا يُرسل شيئًا، وكلتا الحالتين تمرّان بصمت
 * حتى يستقبل الموقع زوّارًا حقيقيين. فهذا الأمر يفحص **البيئة** لا الكود.
 *
 * الفحوص الحرجة تُخرج الأمر برمز فشل، فيمكن إيقاف النشر آليًا:
 *   php artisan deploy:check || exit 1
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'فحص جاهزية النشر: الأسرار، التصحيح، البريد، قاعدة البيانات، والبيانات التجريبية';

    /** @var array<int, array{label: string, ok: bool, detail: string, critical: bool}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkEnvironment();
        $this->checkSecrets();
        $this->checkStorage();
        $this->checkDatabase();
        $this->checkMail();
        $this->checkData();

        return $this->report();
    }

    /** تسجيل نتيجة فحص واحد */
    private function record(string $label, bool $ok, string $detail, bool $critical = true): void
    {
        $this->results[] = [
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
            'critical' => $critical,
        ];
    }

    // ═══════════════ ١. بيئة التطبيق ═══════════════

    private function checkEnvironment(): void
    {
        $env = (string) config('app.env');
        $this->record(
            'APP_ENV = production',
            $env === 'production',
            $env === 'production' ? 'البيئة إنتاجية' : "القيمة الحالية: {$env}",
        );

        $debug = (bool) config('app.debug');
        $this->record(
            'APP_DEBUG معطّل',
            ! $debug,
            $debug ? 'مفعّل — يعرض مسارات الملفات وبيانات الطلب للزوّار' : 'معطّل',
        );

        $key = (string) config('app.key');
        $this->record(
            'APP_KEY مضبوط',
            $key !== '',
            $key !== '' ? 'مضبوط' : 'فارغ — الجلسات والكلمات المشفَّرة كلها باطلة',
        );

        $this->checkUrl();
    }

    /**
     * عنوان الموقع: نطاق حقيقي لا نفق مؤقت.
     *
     * روابط الأنفاق (trycloudflare/ngrok/localhost) تنتهي صلاحيتها أو تُغلق،
     * وروابط التأكيد وصور المنتجات تُبنى منه، فتنكسر كلها.
     */
    private function checkUrl(): void
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: '';

        $temporary = (bool) preg_match('#(trycloudflare|ngrok|localhost|127\.0\.0\.1|\.test$|\.local$)#i', $host);
        $insecure = $scheme !== 'https';

        $ok = ! $temporary && ! $insecure;

        $detail = match (true) {
            $temporary => "عنوان مؤقت أو محلي: {$url}",
            $insecure => "ليس HTTPS: {$url}",
            default => $url,
        };

        // تحذير لا فشل: قد ينشر على نطاق فرعي للتجربة
        $this->record('APP_URL نطاق حقيقي', $ok, $detail, critical: false);
    }

    // ═══════════════ ٢. الأسرار ═══════════════

    /**
     * نسخ `.env` الاحتياطية في جذر المشروع تحوي APP_KEY وأسرارًا.
     * خطرها الحقيقي هنا: النشر يتم عبر FTP، فأي رفع كامل للمجلد ينشرها.
     */
    private function checkSecrets(): void
    {
        $leftovers = [];

        foreach ((array) glob(base_path('.env.bak*')) as $file) {
            $leftovers[] = basename($file);
        }

        foreach ((array) glob(base_path('.env.*.bak')) as $file) {
            $leftovers[] = basename($file);
        }

        $leftovers = array_values(array_unique($leftovers));

        $this->record(
            'لا نسخ .env احتياطية في الجذر',
            $leftovers === [],
            $leftovers === []
                ? 'الجذر نظيف'
                : 'احذفها أو انقلها خارج المشروع: '.implode(', ', $leftovers),
        );

        // `.env` نفسه يجب ألا يكون مقروءًا من الويب: مجلد النشر هو public/،
        // لكن بعض الاستضافات تخدم الجذر كله، فالفحص يستحق.
        $this->record(
            'ملف .env غير مكشوف في public/',
            ! is_file(public_path('.env')),
            is_file(public_path('.env')) ? 'توجد نسخة داخل public/ — مكشوفة للويب' : 'غير مكشوف',
        );
    }

    // ═══════════════ ٣. مجلدات قابلة للكتابة ═══════════════

    private function checkStorage(): void
    {
        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public/storage' => public_path('storage'),
        ];

        foreach ($paths as $label => $path) {
            $writable = is_dir($path) && is_writable($path);
            $this->record(
                "مجلد {$label} قابل للكتابة",
                $writable,
                $writable ? 'قابل للكتابة' : "غير قابل للكتابة: {$path}",
            );
        }
    }

    // ═══════════════ ٤. قاعدة البيانات ═══════════════

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->record('الاتصال بقاعدة البيانات', true, (string) config('database.default'));
        } catch (\Throwable $e) {
            $this->record('الاتصال بقاعدة البيانات', false, $e->getMessage());

            return;
        }

        // SQLite مقبولة للتجربة، لكن أقفال الكتابة تعطّل الطلبات المتزامنة
        $driver = (string) config('database.default');
        $this->record(
            'قاعدة بيانات مناسبة للإنتاج',
            $driver !== 'sqlite',
            $driver === 'sqlite'
                ? 'SQLite — أقفال الكتابة تعطّل الطلبات المتزامنة؛ استخدم MySQL'
                : $driver,
            critical: false,
        );
    }

    // ═══════════════ ٥. البريد ═══════════════

    /**
     * `MAIL_MAILER=log` يكتب الرسائل في السجل ولا يُرسلها — فتفشل استعادة
     * كلمة المرور بصمت بينما الواجهة توحي بأنها تعمل.
     */
    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');
        $this->record(
            'البريد مُعدّ للإرسال الفعلي',
            $mailer !== 'log' && $mailer !== 'array',
            in_array($mailer, ['log', 'array'], true)
                ? "القيمة: {$mailer} — الرسائل تُسجَّل ولا تُرسل؛ استعادة كلمة المرور تفشل بصمت"
                : $mailer,
        );

        $from = (string) config('mail.from.address');
        $placeholder = $from === '' || str_contains($from, 'example.com');
        $this->record(
            'عنوان المُرسِل مضبوط',
            ! $placeholder,
            $placeholder ? 'القيمة: '.($from ?: 'فارغ') : $from,
            critical: false,
        );
    }

    // ═══════════════ ٦. البيانات ═══════════════

    /** متجر بلا منتجات ولا وسائط لا يُباع منه شيء — فحص تجاري لا تقني */
    private function checkData(): void
    {
        if (! $this->databaseIsReachable()) {
            return;
        }

        try {
            $products = Product::count();
            $this->record(
                'يوجد منتجات في المتجر',
                $products > 0,
                $products > 0 ? "{$products} منتجًا" : 'لا يوجد أي منتج — المتجر فارغ',
                critical: false,
            );

            $orphans = OrderItem::whereNull('product_id')->count();
            $this->record(
                'طلبات بلا عناصر يتيمة',
                $orphans === 0,
                $orphans === 0
                    ? 'لا عناصر يتيمة'
                    : "{$orphans} عنصرًا يشير إلى منتج محذوف — بيانات تجريبية يجب تنظيفها",
                critical: false,
            );
        } catch (\Throwable $e) {
            $this->record('فحص البيانات', false, $e->getMessage(), critical: false);
        }
    }

    private function databaseIsReachable(): bool
    {
        foreach ($this->results as $result) {
            if ($result['label'] === 'الاتصال بقاعدة البيانات') {
                return $result['ok'];
            }
        }

        return false;
    }

    // ═══════════════ التقرير ═══════════════

    private function report(): int
    {
        $this->newLine();
        $this->line('  فحص جاهزية النشر — NADAF');
        $this->newLine();

        foreach ($this->results as $result) {
            $mark = $result['ok'] ? '<info>✓</info>' : '<fg=red>✗</fg=red>';
            $this->line(sprintf('  %s  %s — %s', $mark, $result['label'], $result['detail']));
        }

        $failed = array_values(array_filter($this->results, fn ($r) => ! $r['ok']));
        $critical = array_values(array_filter($failed, fn ($r) => $r['critical']));
        $warnings = array_values(array_filter($failed, fn ($r) => ! $r['critical']));

        $this->newLine();

        if ($failed === []) {
            $this->info('  كل الفحوص ناجحة — المشروع جاهز للنشر.');

            return self::SUCCESS;
        }

        $this->error(sprintf('  %d فحصًا حرجًا فاشلًا، %d تحذيرًا.', count($critical), count($warnings)));

        if ($critical !== []) {
            $this->line('  يجب إصلاح الفحوص الحرجة قبل فتح المتجر:');
            foreach ($critical as $item) {
                $this->line("    • {$item['label']}: {$item['detail']}");
            }
        }

        if ($warnings !== []) {
            $this->line('  تحذيرات (لا تمنع النشر لكن تستحق النظر):');
            foreach ($warnings as $item) {
                $this->line("    • {$item['label']}: {$item['detail']}");
            }
        }

        $this->newLine();

        return $critical === [] ? self::SUCCESS : self::FAILURE;
    }
}
