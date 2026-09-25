<?php

namespace Tests\Feature;

use App\Filament\Resources\SlideResource;
use App\Filament\Resources\SlideResource\Pages\ManageSlides;
use App\Models\Slide;
use App\Models\User;
use App\Support\UploadLimits;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * السلايدر — الشريط الكبير أعلى الصفحة الرئيسية.
 *
 * كل اختبار هنا يقابل ثغرة كانت موجودة فعلًا:
 *   • شريحة بلا وسيط تُعرض سوداء  ⇒ لا تُحفظ
 *   • نص الزر مكتوب في الكود       ⇒ صار من اللوحة
 *   • لا سطر توضيحي               ⇒ صار حقلًا
 *   • المدة ثابتة ٦ ثوانٍ          ⇒ صارت لكل شريحة
 */
class SliderTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function slide(array $attrs = []): Slide
    {
        return Slide::create(array_merge([
            'title_ar' => 'شريحة',
            'image_path' => 'images/placeholders/p-tie-1.svg',
            'is_active' => true,
        ], $attrs));
    }

    // ═══════════════ الوسيط ═══════════════

    public function test_a_slide_without_any_media_is_not_displayable(): void
    {
        $slide = $this->slide(['image_path' => null]);

        $this->assertFalse($slide->hasMedia(), 'بلا صورة ولا فيديو لا شيء يُعرض للعميل');
    }

    public function test_a_slide_with_only_an_image_is_displayable(): void
    {
        $this->assertTrue($this->slide()->hasMedia());
    }

    public function test_a_slide_with_only_a_video_is_displayable(): void
    {
        $slide = $this->slide(['image_path' => null, 'video_path' => 'slides/x.mp4']);

        $this->assertTrue($slide->hasMedia());
    }

    public function test_the_form_refuses_a_slide_without_any_media(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // بلا فيديو ⇒ الصورة إلزامية، فلا تُحفظ شريحة فارغة
        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'شريحة بلا وسيط',
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertHasActionErrors(['image_path']);
    }

    public function test_the_form_accepts_a_slide_with_a_video_and_no_image(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // مع فيديو، الصورة اختيارية (صورة غلاف)
        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'شريحة فيديو',
                'video_path' => ['slides/x.mp4'],
                'is_active' => true,
                'sort_order' => 0,
                'duration_seconds' => 6,
            ])
            ->assertHasNoActionErrors();
    }

    // ═══════════════ النص والزر ═══════════════

    public function test_the_button_text_comes_from_the_slide_not_the_code(): void
    {
        $slide = $this->slide([
            'link' => '/category/tie',
            'button_text_ar' => 'اكتشف المجموعة',
        ]);

        $this->assertSame('اكتشف المجموعة', $slide->buttonText);
    }

    public function test_a_slide_without_button_text_falls_back_to_the_default(): void
    {
        // القالب يستخدم __('home.shop_now') عند الفراغ
        $this->assertNull($this->slide(['link' => '/x'])->buttonText);
    }

    public function test_the_subtitle_is_available_in_both_languages(): void
    {
        $slide = $this->slide([
            'subtitle_ar' => 'مجموعة جديدة',
            'subtitle_en' => 'New collection',
        ]);

        app()->setLocale('ar');
        $this->assertSame('مجموعة جديدة', $slide->subtitle);

        app()->setLocale('en');
        $this->assertSame('New collection', $slide->subtitle);
    }

    public function test_the_subtitle_falls_back_to_arabic_when_english_is_missing(): void
    {
        $slide = $this->slide(['subtitle_ar' => 'مجموعة جديدة']);

        app()->setLocale('en');
        $this->assertSame('مجموعة جديدة', $slide->subtitle);
    }

    // ═══════════════ المدة ═══════════════

    public function test_each_slide_has_its_own_duration(): void
    {
        $this->assertSame(8, $this->slide(['duration_seconds' => 8])->duration());
        $this->assertSame(3, $this->slide(['duration_seconds' => 3])->duration());
    }

    public function test_the_default_duration_is_six_seconds(): void
    {
        $slide = $this->slide();
        $slide->duration_seconds = null;

        $this->assertSame(Slide::DEFAULT_DURATION, $slide->duration());
    }

    public function test_an_absurd_duration_is_clamped(): void
    {
        // صفر أو فراغ = «لم يُحدَّد» ⇒ الافتراضي ٦
        $this->assertSame(6, $this->slide(['duration_seconds' => 0])->duration());

        // ثانيتان حدّ أدنى (أقل يومض) ودقيقتان حدّ أعلى
        $this->assertSame(2, $this->slide(['duration_seconds' => 1])->duration());
        $this->assertSame(120, $this->slide(['duration_seconds' => 300])->duration());
    }

    /**
     * الحدّ الأعلى كان ٣٠ ثانية، وهو أقصر من فيديو يصل إلى دقيقة: كان الفيديو
     * يُقطع في منتصفه. هذه هي الثغرة التي بلّغ عنها المالك.
     */
    public function test_a_one_minute_video_gets_a_full_minute_on_screen(): void
    {
        $this->assertSame(60, $this->slide(['duration_seconds' => 60])->duration(),
            'فيديو الدقيقة يجب أن يبقى دقيقة كاملة لا ٣٠ ثانية');
        $this->assertSame(90, $this->slide(['duration_seconds' => 90])->duration());
        $this->assertSame(120, $this->slide(['duration_seconds' => 120])->duration());
    }

    // ═══════════════ الرابط وزر التسوق ═══════════════

    /**
     * كان الزر لا يظهر إطلاقًا إن لم يُكتب رابط. الآن لكل شريحة وجهة دائمًا،
     * فلا يبقى السلايدر بلا مدخل إلى البيع.
     */
    public function test_a_slide_without_a_link_still_has_a_destination(): void
    {
        $this->assertSame(Slide::DEFAULT_LINK, $this->slide()->linkUrl());
    }

    public function test_the_default_destination_comes_from_the_settings(): void
    {
        \App\Models\Setting::set('slide_default_link', '/c/karavat');

        $this->assertSame('/c/karavat', $this->slide()->linkUrl());
    }

    public function test_a_slide_with_its_own_link_keeps_it(): void
    {
        $this->assertSame('/p/karavat-harir', $this->slide(['link' => '/p/karavat-harir'])->linkUrl());
    }

    /**
     * كانت قاعدة التحقق ترفض كل ما لا يبدأ بـ«/» أو «http» — وترفض معها
     * `#categories` التي يستخدمها القالب نفسه. صارت تُطبَّع بدل أن تُرفض.
     */
    public function test_the_link_is_normalized_instead_of_rejected(): void
    {
        $cases = [
            '/category/tie' => '/category/tie',          // مسار داخلي — كما هو
            '#categories' => '#categories',              // مرساة — كما هي
            'https://example.com' => 'https://example.com',
            'http://example.com' => 'http://example.com',
            'tel:+963999' => 'tel:+963999',
            'mailto:a@b.com' => 'mailto:a@b.com',
            'www.nadaf.com' => 'https://www.nadaf.com',   // نطاق مجرّد ⇒ https
            'nadaf.com/sale' => 'https://nadaf.com/sale',
            'category/tie' => '/category/tie',            // مسار بلا شرطة ⇒ شرطة
            '  /category/tie  ' => '/category/tie',       // فراغات زائدة تُقلَّم
            '' => null,
            '   ' => null,
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, Slide::normalizeLink($input), "تطبيع «{$input}»");
        }
    }

    public function test_the_form_accepts_an_anchor_and_a_bare_domain(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'مرساة',
                'image_path' => ['images/placeholders/p-tie-1.svg'],
                'link' => '#categories',
                'is_active' => true,
                'sort_order' => 0,
                'duration_seconds' => 6,
            ])
            ->assertHasNoActionErrors();

        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'نطاق مجرّد',
                'image_path' => ['images/placeholders/p-tie-1.svg'],
                'link' => 'www.nadaf.com',
                'is_active' => true,
                'sort_order' => 0,
                'duration_seconds' => 6,
            ])
            ->assertHasNoActionErrors();

        // النطاق المجرّد يُصحَّح عند الحفظ لا يُحفظ كما كُتب
        $this->assertSame('#categories', Slide::where('title_ar', 'مرساة')->value('link'));
        $this->assertSame('https://www.nadaf.com', Slide::where('title_ar', 'نطاق مجرّد')->value('link'));
    }

    /** الشريحة بلا رابط يجب أن تُعرض بزر يحمل الوجهة الافتراضية */
    public function test_the_storefront_shows_the_button_even_without_a_link(): void
    {
        $this->slide(['title_ar' => 'بلا رابط', 'link' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('بلا رابط', escape: false)
            ->assertSee('href="'.Slide::DEFAULT_LINK.'"', escape: false)
            ->assertSee('تسوق الآن', escape: false);
    }

    /**
     * الوجهة الافتراضية تُضبط من صفحة الإعدادات العامة. كان الخيط الوحيد
     * غير المُتحقَّق منه في جولة الفيديو: أُضيف الحقل إلى النموذج، ولم
     * يُختبر أنه **يُحفظ** ثم **يُقرأ** في المتجر.
     */
    public function test_the_default_destination_is_editable_from_the_settings_page(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Pages\Settings::class)
            ->assertOk()
            ->assertSee('وجهة زر', escape: false)      // الحقل معروض
            ->fillForm(['slide_default_link' => '/c/karavat'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('/c/karavat', \App\Models\Setting::get('slide_default_link'),
            'الإعداد يجب أن يُكتب في جدول settings');

        // ويُقرأ فعلًا: شريحة بلا رابط ترث الوجهة الجديدة
        $this->assertSame('/c/karavat', $this->slide()->linkUrl());
    }

    /** وترك الحقل فارغًا يعود إلى الافتراضي لا إلى رابط مكسور */
    public function test_clearing_the_default_destination_falls_back_to_the_constant(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Pages\Settings::class)
            ->fillForm(['slide_default_link' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Slide::DEFAULT_LINK, $this->slide()->linkUrl(),
            'الفراغ يعود إلى #categories لا إلى رابط فارغ');
    }

    public function test_the_link_accepts_internal_and_external_urls(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // رابط داخلي — الأكثر استخدامًا في السلايدر
        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'رابط داخلي',
                'image_path' => ['images/placeholders/p-tie-1.svg'],
                'link' => '/category/tie',
                'is_active' => true,
                'sort_order' => 0,
                'duration_seconds' => 6,
            ])
            ->assertHasNoActionErrors();

        // ورابط خارجي كامل
        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'رابط خارجي',
                'image_path' => ['images/placeholders/p-tie-2.svg'],
                'link' => 'https://example.com/sale',
                'is_active' => true,
                'sort_order' => 0,
                'duration_seconds' => 6,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(2, Slide::whereIn('title_ar', ['رابط داخلي', 'رابط خارجي'])->count());
    }

    // ═══════════════ اللوحة ═══════════════

    public function test_the_form_has_the_new_sections_and_preview(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Livewire::test(ManageSlides::class)
            ->mountAction('create')
            ->html();

        foreach ([
            'الوسيط — صورة أو فيديو',
            'النص الظاهر',
            'الزر والرابط',
            'العرض والترتيب',
            'معاينة',
            'السطر التوضيحي بالعربية',
            'نص الزر بالعربية',
            'مدة بقاء الشريحة',
            'الرابط عند النقر (اختياري)',
            // النصوص القديمة كانت تقول «يُعرض بلا صوت» و«١٠ ثوانٍ أو أقل» —
            // وهي أوصاف لم تعد صحيحة بعد إضافة زر الصوت ورفع حدّ الرفع
            'حتى دقيقة كاملة',
            'المتصفحات تمنع التشغيل التلقائي بالصوت',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "القسم أو الحقل «{$label}» مفقود");
        }

        // النص القديم يجب أن يكون قد زال، لا أن يبقى بجانب الجديد
        $this->assertStringNotContainsString('يُعرض بلا صوت', $html, 'الوصف القديم ما زال في النموذج');
    }

    public function test_the_admin_can_create_a_complete_slide(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSlides::class)
            ->callAction('create', data: [
                'title_ar' => 'أناقة تليق بك',
                'subtitle_ar' => 'مجموعة الكرافات الجديدة',
                'image_path' => ['images/placeholders/p-tie-1.svg'],
                'link' => '/category/tie',
                'button_text_ar' => 'اكتشف المجموعة',
                'duration_seconds' => 9,
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $slide = Slide::where('title_ar', 'أناقة تليق بك')->first();

        $this->assertNotNull($slide);
        $this->assertSame('مجموعة الكرافات الجديدة', $slide->subtitle);
        $this->assertSame('اكتشف المجموعة', $slide->buttonText);
        $this->assertSame(9, $slide->duration());
    }

    // ═══════════════ الصوت ═══════════════

    /**
     * الفيديو لا يمكن أن يعمل بصوت تلقائيًا: كروم وسفاري يحجبان التشغيل
     * التلقائي غير الصامت. فالبديل زر واحد يشغّل الصوت بنقرة — وهذا ما
     * يجعل «فيديو بصوت» ممكنًا أصلًا.
     */
    public function test_a_video_slide_offers_a_sound_button(): void
    {
        $this->slide(['title_ar' => 'بفيديو', 'image_path' => null, 'video_path' => 'slides/x.mp4']);

        $this->get('/')
            ->assertOk()
            ->assertSee('nad-sl-sound', escape: false)
            ->assertSee('toggleSound()', escape: false);
    }

    /** بلا فيديو لا معنى لزر الصوت — فلا يظهر */
    public function test_an_image_only_slider_has_no_sound_button(): void
    {
        $this->slide(['title_ar' => 'صورة فقط', 'image_path' => 'images/placeholders/p-tie-1.svg']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('nad-sl-sound', escape: false);
    }

    /** كل فيديو يحمل ترتيب شريحته، وإلا أُطلق الصوت لفيديو مخفيّ */
    public function test_each_video_carries_its_slide_index(): void
    {
        $this->slide(['title_ar' => 'أول', 'image_path' => null, 'video_path' => 'slides/a.mp4', 'sort_order' => 0]);
        $this->slide(['title_ar' => 'ثانٍ', 'image_path' => null, 'video_path' => 'slides/b.mp4', 'sort_order' => 1]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-slide="0"', $html);
        $this->assertStringContainsString('data-slide="1"', $html);
    }

    // ═══════════════ حدّ الرفع ═══════════════

    public function test_the_upload_ceiling_is_128_megabytes(): void
    {
        $this->assertSame(128, SlideResource::MAX_UPLOAD_MB);
        $this->assertSame(131072, SlideResource::MAX_UPLOAD_KB);
    }

    /**
     * أصل البلاغ: أربع طبقات تحدّ الرفع، وكانت **أقلّها في النموذج**.
     * فيُقبل الملف في الواجهة ثم يُرفض في PHP بلا رسالة. هذا الاختبار
     * يمنع عودة التفاوت: حدّ Livewire لا يجوز أن يقلّ عن حدّ Filament.
     */
    public function test_the_livewire_limit_is_not_below_the_filament_limit(): void
    {
        $rules = (array) config('livewire.temporary_file_upload.rules');

        $max = collect($rules)->first(fn ($rule) => is_string($rule) && str_starts_with($rule, 'max:'));

        $this->assertNotNull($max, 'قاعدة max مفقودة في config/livewire.php');
        $this->assertGreaterThanOrEqual(
            SlideResource::MAX_UPLOAD_KB,
            (int) substr($max, 4),
            'حدّ Livewire أقل من حدّ Filament — سيُرفض في الخلفية ملف يسمح به النموذج',
        );
    }

    /**
     * أصل الحيرة عند المالك: متى تجاوز الطلب post_max_size رمى Laravel
     * PostTooLargeException، وصفحته الافتراضية عامة لا تذكر الحدّ ولا سببه.
     * الآن تقول الرسالة الحدّ الفعلي وسببه.
     */
    public function test_an_oversized_upload_is_explained_in_json(): void
    {
        $request = Request::create('/livewire/upload-file', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = app(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new PostTooLargeException);

        $this->assertSame(413, $response->getStatusCode());

        // الرد JSON مُرمَّز بترميز ASCII، فنقرأ الحقل لا النصّ الخام
        $payload = json_decode($response->getContent(), true);

        $this->assertStringContainsString('أكبر من الحد', $payload['message']);
        $this->assertStringContainsString(
            UploadLimits::human(UploadLimits::effectiveBytes()),
            $payload['message'],
            'الرسالة يجب أن تذكر الحدّ الفعلي لا عبارة عامة',
        );
    }

    public function test_an_oversized_upload_is_explained_on_the_page(): void
    {
        $response = app(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render(Request::create('/admin/slides', 'POST'), new PostTooLargeException);

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringContainsString('أكبر من الحد', $response->getContent());
    }

    public function test_the_size_conversion_reads_php_notation(): void
    {
        $this->assertSame(128 * 1024 * 1024, UploadLimits::toBytes('128M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, UploadLimits::toBytes('2G'));
        $this->assertSame(512 * 1024, UploadLimits::toBytes('512K'));
        $this->assertSame(0, UploadLimits::toBytes('-1'), '‏-1 تعني بلا حدّ');
        $this->assertSame(0, UploadLimits::toBytes(''), 'الفراغ يعني بلا حدّ');
        $this->assertSame('128 ميغابايت', UploadLimits::human(128 * 1024 * 1024));
    }

    /**
     * اللوحة تُنذر المالك إن كان حدّ الاستضافة أقل من الحد الذي يطلبه الحقل —
     * فالتفاوت بين الطبقات هو ما جعل الرفع يفشل بلا تفسير.
     */
    public function test_the_admin_is_warned_when_the_server_limit_is_lower(): void
    {
        $wanted = SlideResource::MAX_UPLOAD_KB * 1024;
        $notice = SlideResource::serverLimitsNotice();

        if (UploadLimits::isBelow($wanted)) {
            $this->assertNotNull($notice, 'حدّ السيرفر أقل فيجب أن يظهر تنبيه');
            $this->assertStringContainsString(UploadLimits::human(UploadLimits::effectiveBytes()), $notice);
        } else {
            $this->assertNull($notice, 'حين يكفي الحدّ لا يزدحم النموذج بتنبيه');
        }
    }

    /** الحدّ الفعلي هو أصغر الحدّين — وهو ما يشعر به المالك لا حدّ الطلب وحده */
    public function test_the_effective_limit_is_the_smaller_of_the_two(): void
    {
        $this->assertSame(
            min(UploadLimits::uploadMaxBytes(), UploadLimits::postMaxBytes()),
            UploadLimits::effectiveBytes(),
        );
    }

    // ═══════════════ موضع وسم «فيديو» ═══════════════

    /**
     * وسم «فيديو» كان **لا يظهر إطلاقًا**، وسببُه دقيق: الخصائص المنطقية
     * (inset-inline-*) تُحسب على اتجاه العنصر نفسه لا على اتجاه الصفحة.
     * والعدّاد يحمل `direction:ltr` ليبقى «01 / 02» بترتيبه، فصار
     * `inset-inline-end` عنده يعني يمينًا لا يسارًا — فانتهى هو والوسم في
     * الزاوية نفسها، والعدّاد فوقه يغطّيه تمامًا.
     *
     * لا يُكشف هذا بقراءة الكود: الوسم موجود في HTML وقاعدته سليمة. أُكشف
     * بالتصوير وحده. وهذا الاختبار يمنع عودة التغطية.
     */
    public function test_the_video_tag_and_the_counter_sit_on_opposite_sides(): void
    {
        $files = glob(public_path('build/assets/app-*.css')) ?: [];

        if ($files === []) {
            $this->markTestSkipped('لا بناء بعد — شغّل npm run build');
        }

        $css = (string) file_get_contents($files[0]);

        $rule = function (string $selector) use ($css): string {
            preg_match('/'.preg_quote($selector, '/').'\{([^}]*)\}/', $css, $m);

            return $m[1] ?? '';
        };

        $tag = $rule('.nad-sl-tag');
        $meta = $rule('.nad-sl-meta');

        $this->assertNotSame('', $tag, 'قاعدة .nad-sl-tag مفقودة من البناء');
        $this->assertNotSame('', $meta, 'قاعدة .nad-sl-meta مفقودة من البناء');

        // العنصران يصرّحان باتجاههما، فيكون موقع كلٍّ منهما معروفًا لا موروثًا
        $this->assertStringContainsString('direction:ltr', $tag,
            'بلا اتجاه صريح يعود الوسم والعدّاد إلى الزاوية نفسها');
        $this->assertStringContainsString('direction:ltr', $meta);

        // وباتجاه واحد، inline-start و inline-end ضلعان متقابلان بالضرورة
        $this->assertStringContainsString('inset-inline-start', $tag);
        $this->assertStringContainsString('inset-inline-end', $meta);
    }
}
