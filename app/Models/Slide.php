<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Slide extends Model
{
    protected $fillable = [
        'title_ar',
        'title_en',
        'subtitle_ar',
        'subtitle_en',
        'image_path',
        'video_path',
        'youtube_url',
        'link',
        'button_text_ar',
        'button_text_en',
        'sort_order',
        'duration_seconds',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_seconds' => 'integer',
    ];

    /** المدة بالثواني — بحدود معقولة حتى لا تعلق الشريحة أو تومض */
    public const DEFAULT_DURATION = 6;

    /**
     * أقصى مدة لبقاء الشريحة.
     *
     * كانت ٣٠ ثانية، وهي أقصر من فيديو يصل إلى دقيقة: كان الفيديو يُقطع
     * في منتصفه قبل أن يُسمع آخره. رُفعت إلى دقيقتين لتستوعب أي مقطع
     * بحدّ الرفع المسموح، مع هامش للمقاطع التي تُقرأ ببطء.
     */
    public const MAX_DURATION = 120;

    /** أقل مدة — أقصر من ثانيتين يومض ولا يُقرأ */
    public const MIN_DURATION = 2;

    /** وجهة الزر حين لا يُكتب رابط للشريحة — قسم الأقسام في الرئيسية */
    public const DEFAULT_LINK = '#categories';

    public function duration(): int
    {
        $seconds = (int) ($this->duration_seconds ?: self::DEFAULT_DURATION);

        return max(self::MIN_DURATION, min(self::MAX_DURATION, $seconds));
    }

    /** هل الشريحة قابلة للعرض؟ بلا صورة ولا فيديو (مرفوع أو يوتيوب) لا شيء يُعرض */
    public function hasMedia(): bool
    {
        return filled($this->video_path) || $this->hasYoutube() || filled($this->image_path);
    }

    public function getTitleAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->title_en ?: $this->title_ar)
            : $this->title_ar;
    }

    public function getSubtitleAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->subtitle_en ?: $this->subtitle_ar)
            : $this->subtitle_ar;
    }

    /** نص الزر — فارغ يعني «لا زر»، ويُقرأ من الإعداد العام إن لم يُحدَّد */
    public function getButtonTextAttribute(): ?string
    {
        $text = app()->getLocale() === 'en'
            ? ($this->button_text_en ?: $this->button_text_ar)
            : $this->button_text_ar;

        return filled($text) ? $text : null;
    }

    /**
     * وجهة زر «تسوق الآن».
     *
     * كان الزر لا يظهر إطلاقًا إن لم يُكتب رابط لكل شريحة، فيبقى السلايدر
     * بلا مدخل للبيع. الآن لكل شريحة وجهة دائمًا: رابطها إن وُجد، وإلا
     * الرابط الافتراضي من الإعدادات.
     */
    public function linkUrl(): string
    {
        return filled($this->link)
            ? $this->link
            : (string) setting('slide_default_link', self::DEFAULT_LINK);
    }

    /** نص الزر — وإن لم يُكتب يرث النص العام */
    public function buttonLabel(): string
    {
        return $this->buttonText ?? __('home.shop_now');
    }

    /** هل للشريحة فيديو؟ مرفوع أو من يوتيوب — الصوت والغلاف يتعلقان به وحده */
    public function hasVideo(): bool
    {
        return filled($this->video_path) || $this->hasYoutube();
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return str_starts_with($this->image_path, 'images/')
            ? asset($this->image_path)
            : Storage::url($this->image_path);
    }

    public function videoUrl(): ?string
    {
        if (! $this->video_path) {
            return null;
        }

        return str_starts_with($this->video_path, 'images/')
            ? asset($this->video_path)
            : Storage::url($this->video_path);
    }

    /**
     * هل للشريحة فيديو يوتيوب مضمَّن؟
     *
     * يوتيوب فيديو كأي وسائط أخرى من منظور الشريحة: له شارة «فيديو»
     * ويتصرف مثل video_path في القالب. الفرق الوحيد أن مصدره iframe
     * لا وسم video — انظر hero.blade.php.
     */
    public function hasYoutube(): bool
    {
        return filled($this->youtube_url) && $this->youtubeEmbedUrl() !== null;
    }

    /**
     * استخراج معرف فيديو يوتيوب من أي شكل مألوف.
     *
     * يقبل: youtube.com/watch?v= · youtu.be/ · youtube.com/embed/
     *       youtube.com/shorts/ · والمعرف المجرّد وحده (11 حرفًا).
     * ويعيد null إن لم يعثر على معرف صالح — فلا يُعرض iframe نصف مكسور.
     *
     * القيد: لا نتعامل مع روابط القنوات أو التشغيل التلقائي للقوائم،
     * فهي لا تُعطي معرفًا واحدًا قابلًا للتضمين.
     */
    public static function extractYoutubeId(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // معرّف مجرّد (11 حرفًا من الأرقام والحروف والشرطة والشرطة السفلية)
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $value)) {
            return $value;
        }

        // youtu.be/VIDEOID — أقصر أشكال المشاركة
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{11})~', $value, $m)) {
            return $m[1];
        }

        // youtube.com/watch?v=VIDEOID
        if (preg_match('~[?&]v=([A-Za-z0-9_-]{11})~', $value, $m)) {
            return $m[1];
        }

        // youtube.com/embed/VIDEOID أو /shorts/VIDEOID
        if (preg_match('~youtube\.com/(?:embed|shorts)/([A-Za-z0-9_-]{11})~', $value, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * رابط التضمين الجاهز لـ iframe — أو null إن لم يكن رابطًا صالحًا.
     *
     * معاملات استعلام مختارة بعناية لبيئة السلايدر:
     *   • autoplay=1 mute=1 — التشغيل التلقائي الصامت (المتصفحات لا تقبل
     *     غيره)، ومفتاح الصوت لدينا يرفع الكتم عبر postMessage
     *   • loop=1 playlist=<id> — التكرار لا يعمل بلا playlist عند يوتيوب
     *   • rel=0 — لا إعلانات لقنوات أخرى في نهاية المقطع
     *   • modestbranding=1 — أقل شعارات posibles
     *   • playsinline=1 — لا ملء الشاشة الإجباري على iOS
     */
    public function youtubeEmbedUrl(): ?string
    {
        $id = self::extractYoutubeId($this->youtube_url);

        if ($id === null) {
            return null;
        }

        return 'https://www.youtube.com/embed/'.$id
            .'?autoplay=1&mute=1&loop=1&playlist='.$id
            .'&rel=0&modestbranding=1&playsinline=1';
    }

    /**
     * الرابط الأصلي الذي كتبه المالك — لعرضه في اللوحة والمعاينة فقط.
     */
    public function youtubeSourceUrl(): ?string
    {
        $value = trim((string) $this->youtube_url);

        return $value !== '' ? $value : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * تطبيع الرابط عند الحفظ بدل رفضه.
     *
     * كان النموذج يرفض كل ما لا يبدأ بـ«/» أو «http»، فيُصطدم المالك
     * برسالة خطأ وهو يكتب `#categories` أو `www.nadaf.com` — مع أن
     * القالب نفسه يستخدم `#categories`. الرفض هنا عبء بلا فائدة،
     * فصار يُقبل الشكل المألوف ويُصحَّح بصمت إلى شكل صالح للنقر.
     */
    public function setLinkAttribute(?string $value): void
    {
        $this->attributes['link'] = self::normalizeLink($value);
    }

    public static function normalizeLink(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // أشكال تُكتب كما هي: مسار داخلي، مرساة، بروتوكول، هاتف، بريد
        if (preg_match('~^(/|#|//|https?://|tel:|mailto:|sms:)~i', $value)) {
            return $value;
        }

        // نطاق مجرّد: example.com أو www.example.com/sale ⇒ نضيف https
        if (preg_match('~^[a-z0-9-]+(\.[a-z0-9-]+)+(/.*)?$~i', $value)) {
            return 'https://'.$value;
        }

        // مسار داخلي كُتب بلا شرطة بادئة: category/tie ⇒ /category/tie
        return '/'.ltrim($value, '/');
    }
}
