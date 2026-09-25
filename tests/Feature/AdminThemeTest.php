<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * سلامة ثيم اللوحة — فحص ملف CSS نفسه.
 *
 * سبب وجوده: ثلاثة أعراض رآها المالك في المتصفح —
 *   • نصوص التبويبات تختفي عند مرور المؤشر
 *   • نصوص أزرار الجدول («عرض» · «الفاتورة») تبدو شبه شفافة
 *   • حقل «لكل صفحة» يظهر مرتين بسهمين
 * — وكان أصلها كلّه في `resources/css/admin.css`:
 *   ١) سلّم رمادي **مقلوب الاتجاه** (gray-200 داكن مع أن Filament يستخدمه لنص المرور)
 *   ٢) استيراد ناقص لـ CSS مكوّنات Filament (فحقل «لكل صفحة» صار نسختين)
 *
 * اختبارات الواجهة لا ترى الألوان، وهذا الاختبار يقرأ الثيم مباشرةً ويمنع رجوع الخطأين.
 */
class AdminThemeTest extends TestCase
{
    private function css(): string
    {
        $path = resource_path('css/admin.css');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_it_imports_filaments_component_css(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(
            'vendor/filament/support/resources/css/components/pagination.css',
            $css,
            'بدون هذا الاستيراد تُفقد قواعد إخفاء النسخة المدمجة، فيظهر حقل «لكل صفحة» مرتين.'
        );
    }

    public function test_the_gray_scale_keeps_tailwinds_semantic_direction(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/--color-gray-200:\s*#[0-9A-Fa-f]{6}/', $css, '--color-gray-200 غير معرّف');
        $this->assertMatchesRegularExpression('/--color-gray-800:\s*#[0-9A-Fa-f]{6}/', $css, '--color-gray-800 غير معرّف');

        preg_match('/--color-gray-200:\s*(#[0-9A-Fa-f]{6})/', $css, $m200);
        preg_match('/--color-gray-800:\s*(#[0-9A-Fa-f]{6})/', $css, $m800);

        $this->assertGreaterThan(
            0.6,
            $this->luminance($m200[1]),
            'gray-200 يجب أن يبقى فاتحًا: Filament يستخدمه لنص المرور dark:group-hover:text-gray-200.'
        );

        $this->assertLessThan(
            0.2,
            $this->luminance($m800[1]),
            'gray-800 يجب أن يبقى داكنًا: Filament يستخدمه لأسطح dark:bg-gray-800.'
        );
    }

    public function test_the_whole_low_end_of_the_gray_scale_is_light(): void
    {
        $css = $this->css();

        foreach ([50, 100, 200, 300] as $step) {
            preg_match("/--color-gray-{$step}:\\s*(#[0-9A-Fa-f]{6})/", $css, $m);

            $this->assertNotEmpty($m, "--color-gray-{$step} غير معرّف");
            $this->assertGreaterThan(
                0.45,
                $this->luminance($m[1]),
                "gray-{$step} داكن — وهو من عائلة النصوص في Filament، فتختفي النصوص عند المرور."
            );
        }
    }

    public function test_selects_hide_the_native_arrow(): void
    {
        $css = str_replace(' ', '', $this->css());

        $this->assertStringContainsString(
            'appearance:none',
            $css,
            'بدون appearance:none يظهر سهم المتصفح إلى جانب سهم Filament ⇒ سهمان.'
        );
    }

    public function test_the_identity_stays_brass_not_purple(): void
    {
        // نفحص الأنماط الفعلية لا التعليقات: ملف الثيم يشرح تاريخ البنفسجي
        // في تعليق، وذكرُه هناك مفيد ولا يُلوّن شيئًا.
        $css = preg_replace('~/\*.*?\*/~s', '', $this->css());

        foreach (['#6366F1', '#818CF8', '#A855F7', '99,102,241', '99, 102, 241'] as $purple) {
            $this->assertStringNotContainsString(
                $purple,
                $css,
                "لون بنفسجي {$purple} عاد إلى أنماط الثيم — الهوية نحاسية (#D2A24E)."
            );
        }

        $this->assertStringContainsString('#D2A24E', $css, 'اللون النحاسي الأساسي مفقود من الثيم.');
    }

    public function test_it_imports_the_tailwind_v4_compatibility_layer(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(
            "filament-v4-compat.css",
            $css,
            'بدون طبقة التوافق لا تعمل شبكة أعمدة Filament إطلاقًا (Tailwind v4 كسر اختصار v3).'
        );

        $this->assertFileExists(resource_path('css/filament-v4-compat.css'));
    }

    /**
     * Tailwind v4 يُخرج `grid-cols-[--cols-default]` هكذا:
     *     grid-template-columns: --cols-default        ← قيمة غير صالحة، يتجاهلها المتصفح
     * بدل v3 التي كانت تُخرج `var(--cols-default)`.
     *
     * والنتيجة أن شبكة اللوحة كلها تسقط: تتقلّص البطاقات إلى عرض محتواها.
     * هذا الاختبار يضمن أن كل صنف يستخدمه Filament بهذا الشكل له مقابل صحيح.
     */
    public function test_the_compat_layer_covers_every_variable_class_filament_uses(): void
    {
        // نُزيل التعليقات والمسافات: المهم وجود التعريف لا تنسيقه
        $compat = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(resource_path('css/filament-v4-compat.css')));
        $compat = (string) preg_replace('/\s+/', '', $compat);

        $suffixes = ['default', 'sm', 'md', 'lg', 'xl'];
        $properties = [
            'grid-template-columns:var(--cols-%s)',
            'columns:var(--cols-%s)',
            'grid-column:var(--col-span-%s)',
            'grid-column-start:var(--col-start-%s)',
        ];

        $missing = [];

        foreach ($suffixes as $suffix) {
            foreach ($properties as $property) {
                $declaration = sprintf($property, $suffix);

                if (! str_contains($compat, $declaration)) {
                    $missing[] = $declaration;
                }
            }
        }

        foreach (['-webkit-line-clamp:var(--line-clamp)', 'width:var(--sidebar-width)'] as $extra) {
            if (! str_contains($compat, $extra)) {
                $missing[] = $extra;
            }
        }

        $this->assertSame([], $missing, "قواعد توافق ناقصة:\n".implode("\n", $missing));
    }

    /**
     * القواعد يجب أن تكون **خارج** @layer، لأن غير المُدرَج يتقدّم على الطبقات
     * فيغلب مخرجات Tailwind المعطوبة. ونقلها إلى @layer utilities يُبطلها.
     */
    public function test_the_compat_rules_are_unlayered(): void
    {
        // نفحص الأنماط لا التعليقات: الشرح يذكر @layer تحذيرًا ولا يُنشئ طبقة
        $compat = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(resource_path('css/filament-v4-compat.css')));

        $this->assertStringNotContainsString('@layer', $compat, 'قواعد التوافق يجب ألا تكون داخل @layer.');
        $this->assertStringContainsString('body.fi-panel-admin', $compat, 'المحدِّد يجب أن يكون موسّعًا ليتقدّم على قاعدة Tailwind.');
    }

    /**
     * فخّ حقيقي وقعتُ فيه: كتابة `**&#47;` داخل تعليق CSS تُنهي التعليق مبكرًا
     * فيصير بقيّته كودًا ⇒ خطأ صياغة يُسقط الملف كله بصمت.
     * (نفس الفخّ في تعليقات Blade — احذر المُغلِق داخل النص.)
     */
    public function test_no_css_comment_closes_itself_early(): void
    {
        foreach (['admin.css', 'filament-v4-compat.css'] as $name) {
            $css = (string) file_get_contents(resource_path('css/'.$name));

            // نتجاهل تعليمات @source: تحتوي مسارًا مثل filament/**/*.blade.php
            // والنجمة-الشرطة-المائلة داخله جزء من المسار لا بداية تعليق.
            $css = (string) preg_replace('/@source[^;]*;/', '', $css);

            $offset = 0;

            while (($start = strpos($css, '/*', $offset)) !== false) {
                // تعليق حقيقي = `/*` يسبقه فراغ أو `}` أو `;` أو بداية الملف
                $prev = $start > 0 ? $css[$start - 1] : "\n";

                if (! in_array($prev, ["\n", "\r", ' ', "\t", '}', ';', ''], true)) {
                    $offset = $start + 2;

                    continue;
                }

                $end = strpos($css, '*/', $start + 2);

                $this->assertNotFalse($end, "تعليق غير مغلق في {$name}");

                $inner = substr($css, $start + 2, $end - $start - 2);

                $this->assertStringNotContainsString(
                    '/*',
                    $inner,
                    "تعليق متداخل في {$name} — أول مُغلِق يُنهي التعليق الأب، فيصير بقيّته كودًا."
                );

                $offset = $end + 2;
            }
        }
    }

    /**
     * حماية من الخطأ الذي أخفى صفحة «منتجاتي» كاملة.
     *
     * القوالب تستخدم أصناف `.nad-*`، وبعضها معرَّف في `app.css` — وهو ملف
     * المتجر، **واللوحة لا تُحمّله إطلاقًا** (viteTheme = admin.css وحده).
     * فكانت الصفحة تُبنى في HTML كاملة (٧ بطاقات) لكن بلا تنسيق: البطاقة تفقد
     * `position:relative`، فيغطّي عنصرها الاحتياطي `absolute inset-0` الصفحة
     * بتدرّج داكن ⇒ **صفحة تبدو فارغة تمامًا** رغم أن البيانات موجودة.
     *
     * هذا الاختبار يمنع تكرارها: كل صنف `nad-*` تستخدمه قوالب اللوحة **يجب**
     * أن يكون معرَّفًا في CSS تُحمّله اللوحة فعلًا.
     */
    public function test_every_nad_class_used_by_panel_templates_is_defined_in_the_panel_css(): void
    {
        // ما تُحمّله اللوحة فعلًا
        $panelCss = $this->css()."\n";
        foreach (['nad-components.css', 'filament-v4-compat.css'] as $imported) {
            $path = resource_path('css/'.$imported);
            $this->assertFileExists($path, "الملف {$imported} مفقود");
            $panelCss .= (string) file_get_contents($path);
        }

        $this->assertStringContainsString(
            'nad-components.css',
            $this->css(),
            'admin.css يجب أن يستورد nad-components.css وإلا فقدت اللوحة هوية المتجر.'
        );

        // الأصناف المستخدمة في قوالب اللوحة
        $used = [];
        $templates = array_merge(
            glob(resource_path('views/filament/pages/*.blade.php')) ?: [],
            glob(resource_path('views/filament/**/*.blade.php')) ?: [],
        );

        foreach ($templates as $template) {
            // نستثني تعليقات Blade: قد يذكر التعليق صنفًا شرحًا لا استخدامًا
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($template));
            preg_match_all('/\bnad-[a-z0-9-]+/', $source, $found);
            $used = array_merge($used, $found[0]);
        }

        $used = array_unique($used);
        $this->assertNotEmpty($used, 'لم أجد أي صنف nad-* في قوالب اللوحة — تحقق من المسارات');

        $undefined = [];

        foreach ($used as $class) {
            // معرَّف؟ إما كقاعدة `.class` أو كرمز لون `--color-class`
            if (str_contains($panelCss, '.'.$class) || str_contains($panelCss, '--color-'.$class)) {
                continue;
            }

            $undefined[] = $class;
        }

        $this->assertSame(
            [],
            $undefined,
            "أصناف تستخدمها قوالب اللوحة وليست معرَّفة في CSS اللوحة:\n"
            .implode("\n", $undefined)
            ."\n(ستُعرض الصفحة بلا تنسيق — وقد تبدو فارغة تمامًا)"
        );
    }

    private function luminance(string $hex): float
    {
        $rgb = sscanf($hex, '#%02x%02x%02x');

        return (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255;
    }
}
