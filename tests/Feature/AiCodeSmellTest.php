<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ═══ اختبارات أخطاء الكود المولَّد (AI) ═══
 *
 * النظام مبنيّ بالسريع، والكود المولَّد يُنتج أخطاءً **متكرّرة ومعروفة**:
 * ينسى التنظيف، ويترك مسارًا بلا اسم، ويستدعي مفتاح ترجمة غير موجود، ويكتب
 * منطقًا مكرّرًا يتباعد بعد أسبوعين، ويضيف موردًا في اللوحة بلا سياسة.
 *
 * وهذه الأخطاء **لا تظهر في الاختبار الوظيفي**: الصفحة تعمل، لكنها تعمل
 * ناقصة. فهذه الاختبارات تقيس **النمط** لا السلوك — وتمنع تكرار الخطأ نفسه
 * في أي كود جديد يُضاف.
 */
class AiCodeSmellTest extends TestCase
{
    use RefreshDatabase;

    /** كل ملفات PHP والقوالب في المشروع (بلا vendor) */
    private function projectFiles(array $extensions = ['php']): array
    {
        $out = [];

        foreach (['app', 'routes', 'database', 'resources/views', 'bootstrap', 'config'] as $root) {
            $path = base_path($root);

            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $ext = $file->getExtension();

                if (in_array($ext, $extensions, true)
                    || ($ext === 'php' && str_ends_with($file->getFilename(), '.blade.php'))) {
                    $out[] = $file->getPathname();
                }
            }
        }

        return $out;
    }

    // ═══════════════ ١. آثار التصحيح المتروكة ═══════════════

    /**
     * `dd()` أو `dump()` متروك في الكود يوقف الصفحة للمستخدم النهائي.
     * و`console.log` متروك يكشف بنية البيانات لأي زائر.
     */
    public function test_no_debug_statements_are_left_in_the_code(): void
    {
        $offenders = [];

        foreach ($this->projectFiles() as $file) {
            $code = File::get($file);

            // نتجاهل التعليقات — الشرح قد يذكر الدالة بلا أن يستدعيها
            $code = preg_replace('#/\*.*?\*/#s', '', $code);
            $code = preg_replace('#//[^\n]*#', '', $code);

            // ⚠️ حدود الكلمة ضرورية: `ray(` موجودة داخل `array(`،
            // و`dd(` داخل `add(` — والبحث النصّي المجرّد يعطي إنذارات كاذبة.
            $patterns = [
                'dd(' => '/(?<![a-zA-Z_$>])dd\s*\(/',
                'dump(' => '/(?<![a-zA-Z_$>])dump\s*\(/',
                'var_dump(' => '/(?<![a-zA-Z_$>])var_dump\s*\(/',
                'ray(' => '/(?<![a-zA-Z_$>])ray\s*\(/',
                'console.log(' => '/console\.log\s*\(/',
                '->toSql()' => '/->toSql\s*\(/',
            ];

            foreach ($patterns as $label => $regex) {
                if (preg_match($regex, $code)) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' → '.$label;
                }
            }
        }

        $this->assertSame([], $offenders,
            "آثار تصحيح متروكة:\n- ".implode("\n- ", $offenders));
    }

    public function test_no_unfinished_markers_are_left(): void
    {
        $offenders = [];

        foreach ($this->projectFiles() as $file) {
            $code = File::get($file);

            foreach (['TODO', 'FIXME', 'HACK:'] as $marker) {
                if (str_contains($code, $marker)) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' → '.$marker;
                }
            }
        }

        $this->assertSame([], $offenders,
            "علامات عمل غير منجز:\n- ".implode("\n- ", $offenders));
    }

    // ═══════════════ ٢. المسارات ═══════════════

    /** مسار بلا اسم لا يمكن استدعاؤه بـ`route()` — فيُكتب الرابط يدويًّا وينكسر لاحقًا */
    public function test_every_application_route_has_a_name(): void
    {
        $unnamed = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // مسارات الأطراف (Filament · Livewire · الملفات · الفحص) لها أسماء خاصة
            if (preg_match('#^(admin|livewire|storage|up|_)#', $uri)) {
                continue;
            }

            if (! $route->getName()) {
                $unnamed[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $unnamed,
            "مسارات بلا اسم:\n- ".implode("\n- ", $unnamed));
    }

    // ═══════════════ ٣. القوالب ═══════════════

    /** قالب مُشار إليه وغير موجود ⇒ صفحة 500 عند أول زيارة */
    public function test_every_referenced_view_exists(): void
    {
        $missing = [];

        foreach ($this->projectFiles(['php']) as $file) {
            $code = File::get($file);

            preg_match_all(
                "#(?:view|@include|@extends|@includeIf)\(\s*'([a-zA-Z0-9_.\-]+)'#",
                $code,
                $m,
            );

            foreach (array_unique($m[1]) as $view) {
                $path = resource_path('views/'.str_replace('.', '/', $view));

                if (! file_exists($path.'.blade.php') && ! file_exists($path.'.php')) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' → '.$view;
                }
            }
        }

        $this->assertSame([], $missing,
            "قوالب مُشار إليها وغير موجودة:\n- ".implode("\n- ", $missing));
    }

    /** مفتاح ترجمة غير موجود يظهر للعميل كنصّ المفتاح نفسه (`checkout.foo`) */
    public function test_every_translation_key_exists(): void
    {
        $ar = json_decode(File::get(lang_path('ar.json')), true) ?: [];
        $en = json_decode(File::get(lang_path('en.json')), true) ?: [];

        $missing = [];
        $untranslated = [];

        foreach ($this->projectFiles() as $file) {
            $code = File::get($file);

            preg_match_all("#(?:__|@lang|trans)\(\s*'([A-Za-z0-9_.\-]+)'#", $code, $m);

            foreach (array_unique($m[1]) as $key) {
                if (! array_key_exists($key, $ar)) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' → '.$key;
                } elseif (! array_key_exists($key, $en)) {
                    $untranslated[] = $key;
                }
            }
        }

        $this->assertSame([], $missing,
            "مفاتيح ترجمة مفقودة:\n- ".implode("\n- ", $missing));

        $this->assertSame([], array_values(array_unique($untranslated)),
            "مفاتيح بلا ترجمة إنجليزية:\n- ".implode("\n- ", $untranslated));
    }

    // ═══════════════ ٤. قاعدة المشروع: كل مورد له سياسة ═══════════════

    /**
     * ⚠️ قاعدة موثّقة في هذا المشروع: أي مورد في Filament يجب أن يحصل على
     * صلاحية في المصفوفة + سياسة. و`Filament\authorize()` يعيد
     * `Response::allow()` عند **غياب** السياسة — أي أن نسيانها يفتح المورد
     * للجميع بلا أي رسالة. فهذا الاختبار يمنع النسيان.
     */
    public function test_every_filament_resource_has_a_policy(): void
    {
        $resources = [];

        foreach (File::files(app_path('Filament/Resources')) as $file) {
            if (str_ends_with($file->getFilename(), 'Resource.php')) {
                $resources[] = str_replace('.php', '', $file->getFilename());
            }
        }

        $this->assertNotEmpty($resources, 'لم أجد موارد');

        $missing = [];

        foreach ($resources as $resource) {
            $model = str_replace('Resource', '', $resource);
            $policy = app_path('Policies/'.$model.'Policy.php');

            if (! file_exists($policy)) {
                $missing[] = $resource.' → لا سياسة ('.$model.'Policy.php)';
            }
        }

        $this->assertSame([], $missing,
            "موارد بلا سياسة ⇒ مفتوحة للجميع بلا رسالة:\n- ".implode("\n- ", $missing));
    }

    /** وكل سياسة ترث `StaffPolicy` ولا تكرّر منطق الصلاحيات */
    public function test_policies_extend_the_shared_staff_policy(): void
    {
        $offenders = [];

        foreach (File::files(app_path('Policies')) as $file) {
            $code = File::get($file->getPathname());

            if (! str_contains($code, 'StaffPolicy')) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders,
            "سياسات لا ترث StaffPolicy (تكرار للمنطق):\n- ".implode("\n- ", $offenders));
    }

    // ═══════════════ ٥. التهجيرات ═══════════════

    /**
     * تهجير بلا `down()` لا يمكن التراجع عنه — وهو أول ما ينساه الكود المولَّد.
     */
    public function test_every_migration_can_be_rolled_back(): void
    {
        $offenders = [];

        foreach (File::files(database_path('migrations')) as $file) {
            $code = File::get($file->getPathname());

            if (! str_contains($code, 'function down')) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders,
            "تهجيرات بلا down():\n- ".implode("\n- ", $offenders));
    }

    // ═══════════════ ٦. الأسرار ═══════════════

    /**
     * سرّ مكتوب في الكود لا يمكن تدويره بلا تعديل الكود، ويُرفع إلى أي مستودع.
     */
    public function test_no_secrets_are_hardcoded(): void
    {
        $offenders = [];

        foreach ($this->projectFiles() as $file) {
            // `.env` نفسه مستثنى بطبيعته
            if (str_ends_with($file, '.env')) {
                continue;
            }

            $code = File::get($file);

            // مفاتيح تشبه توكنات تيليجرام: أرقام:حروف
            if (preg_match("/['\"][0-9]{6,12}:[A-Za-z0-9_\-]{30,}['\"]/", $code)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }

            // مفاتيح تبدأ بـsk- أو ما يشبهها
            if (preg_match("/['\"](sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16})['\"]/", $code)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)),
            "أسرار مكتوبة في الكود:\n- ".implode("\n- ", $offenders));
    }

    // ═══════════════ ٧. الأداء: التحميل الكسول ═══════════════

    /**
     * استعلام داخل حلقة = مئات الاستعلامات على صفحة واحدة. المشروع يسجّل
     * تحذيرًا عند التحميل الكسول؛ وهذا الاختبار يجعل التحذير **يُفشل** الصفحة
     * إن ظهر في المسارات الرئيسية.
     */
    public function test_the_main_storefront_pages_do_not_trigger_lazy_loading(): void
    {
        $category = \App\Models\Category::create(['name_ar' => 'ك', 'name_en' => 'C', 'slug' => 'c']);

        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة', 'name_en' => 'Tie',
            'slug' => 'lazy-tie', 'price_usd' => 10, 'is_active' => true,
        ]);

        \App\Models\ProductVariant::create([
            'product_id' => $product->id, 'color' => 'كحلي', 'quantity' => 5,
        ]);

        $warnings = [];

        \Illuminate\Support\Facades\Log::listen(function ($event) use (&$warnings) {
            if (str_contains($event->message ?? '', 'Lazy loading')) {
                $warnings[] = $event->message.' '.json_encode($event->context ?? [], JSON_UNESCAPED_UNICODE);
            }
        });

        foreach (['/', '/p/lazy-tie', '/c/c', '/search?q=كرافة', '/cart'] as $url) {
            $this->get($url);
        }

        $this->assertSame([], array_values(array_unique($warnings)),
            "استعلامات N+1 في الصفحات الرئيسية:\n- ".implode("\n- ", $warnings));
    }

    // ═══════════════ ٨. الوصولية الأساسية ═══════════════

    /**
     * الصور بلا `alt` ونماذج بلا `label` — أول ما يُنسى، ويؤذي قارئ الشاشة.
     */
    public function test_images_have_alt_text(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->projectFiles(['php']) as $file) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }

            $code = File::get($file);

            // ⚠️ لا `[^>]*`: سهم `->` داخل `{{ $x->y }}` فيه `>` فيقطع الوسم
            // عند منتصفه ⇒ إنذار كاذب على كل صورة. فنقف عند أول `>` لا يسبقه `-`.
            preg_match_all('/<img\b.*?(?<!-)>/is', $code, $m);

            $checked += count($m[0]);

            foreach ($m[0] as $tag) {
                if (! str_contains($tag, 'alt=')) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' → '.substr($tag, 0, 70);
                }
            }
        }

        $this->assertSame([], $offenders,
            "صور بلا نصّ بديل:\n- ".implode("\n- ", $offenders));

        // اختبار لا يجد شيئًا قد يكون تعبيره معطوبًا — فيبدو ناجحًا وهو لا يفحص
        $this->assertGreaterThan(0, $checked, 'لم يُفحص أي وسم صورة ⇒ التعبير معطوب');
    }
}
