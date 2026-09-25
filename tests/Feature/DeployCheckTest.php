<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * اختبارات أمر `deploy:check`.
 *
 * ⚠️ الخطر في هذا النوع من الأوامر أنه يبدو مفيدًا وهو لا يفحص شيئًا:
 * إما أن ينجح دائمًا (فلا يمنع نشرًا سيئًا) أو يفشل دائمًا (فيُتجاهل).
 * فالاختباران الأول والأخير هنا يقيسان **الاتجاهين**: يفشل خارج الإنتاج،
 * وينجح حين تُضبط البيئة ضبطًا صحيحًا.
 */
class DeployCheckTest extends TestCase
{
    use RefreshDatabase;

    /** بيئة الاختبار ليست إنتاجية — فالأمر يجب أن يمنع النشر */
    public function test_it_fails_outside_a_production_environment(): void
    {
        $this->artisan('deploy:check')->assertFailed();
    }

    /**
     * نسخة `.env` احتياطية في الجذر تحوي APP_KEY — والنشر عبر FTP
     * يرفعها مع المجلد كله. يجب أن يُبلَّغ عنها بالاسم.
     */
    public function test_it_flags_leftover_env_backups_by_name(): void
    {
        $probe = base_path('.env.bak-probe');
        File::put($probe, 'APP_KEY=probe-value');

        try {
            $this->artisan('deploy:check')
                ->expectsOutputToContain('.env.bak-probe')
                ->assertFailed();
        } finally {
            File::delete($probe);
        }

        // إن بقي الملف فالاختبارات التالية كلها ستفشل بسبب أثر جانبي
        $this->assertFileDoesNotExist($probe, 'الملف التجريبي لم يُحذف — سيسمّم بقية الاختبارات');
    }

    /** متجر بلا منتجات لا يُباع منه شيء — يُبلَّغ عنه ولو كان الإعداد سليمًا */
    public function test_it_reports_an_empty_catalog(): void
    {
        $this->artisan('deploy:check')
            ->expectsOutputToContain('المتجر فارغ');
    }

    /**
     * الجانب الآخر: حين تُضبط البيئة ضبطًا صحيحًا يجب أن **ينجح**،
     * وإلا صار الأمر مجرد إنذار دائم يُتجاهل.
     *
     * قاعدة SQLite تُترك كما هي فهي تحذير لا فشل — ولا يمكن تبديل الاتصال
     * إلى MySQL داخل اختبار يعمل على `:memory:`.
     */
    public function test_it_passes_when_the_environment_is_configured_correctly(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://nadaf.sy',
            'mail.default' => 'smtp',
            'mail.from.address' => 'store@nadaf.sy',
        ]);

        $this->artisan('deploy:check')->assertSuccessful();
    }
}
