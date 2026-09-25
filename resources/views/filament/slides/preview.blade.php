{{--
    معاينة الشريحة داخل نموذج التعديل.
    تحاكي شكل السلايدر في الصفحة الرئيسية: الوسيط + التعتيم + العنوان والزر.

    الزر يظهر دائمًا هنا كما يظهر في المتجر — بلا رابط يرث الافتراضي،
    وتُعرض وجهته تحته ليتأكّد المالك من صحّتها قبل الحفظ.
--}}
@php
    use App\Models\Slide;

    // حالة FileUpload قد تكون مصفوفة أو نصًّا — نتعامل مع الاثنين
    $pick = function ($value): ?string {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    };

    $toUrl = function (?string $path): ?string {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'images/') ? asset($path) : Storage::url($path);
    };

    $imgUrl = $toUrl($pick($image));
    $vidUrl = $toUrl($pick($video));

    // يوتيوب: نص الرابط قد يكون مصفوفة أيضًا (قيمة حقل نصي عادي لا FileUpload)
    $ytRaw = $pick($youtube ?? null);
    $ytId = Slide::extractYoutubeId($ytRaw);
    $ytEmbed = $ytId !== null
        ? 'https://www.youtube.com/embed/'.$ytId.'?autoplay=1&mute=1&loop=1&playlist='.$ytId.'&rel=0&modestbranding=1&playsinline=1'
        : null;

    // يوتيوب يأخذ الأسبقية على الفيديو المرفوع إن كتبه المالك
    $mainIsVideo = $ytEmbed !== null || $vidUrl !== null;

    // الرابط قد يكون مسارًا داخليًا أو مرساة أو نطاقًا مجرّدًا — يُعرض كما هو
    $href = filled($href ?? null) ? $href : null;
@endphp

<div class="rounded-xl overflow-hidden border border-white/10 bg-[#0F1319]">
    <div class="relative" style="aspect-ratio:16/9;max-height:340px">
        @if ($ytEmbed)
            {{-- يوتيوب مضمَّن — يُخدَّم من خوادم يوتيوب فلا مساحة على موقعنا --}}
            <iframe src="{{ $ytEmbed }}" title="معاينة يوتيوب" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture"
                    style="position:absolute;inset:0;width:100%;height:100%"></iframe>
        @elseif ($vidUrl)
            <video src="{{ $vidUrl }}" @if ($imgUrl) poster="{{ $imgUrl }}" @endif
                   muted loop playsinline autoplay
                   style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover"></video>
        @elseif ($imgUrl)
            <img src="{{ $imgUrl }}" alt=""
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
        @else
            <span aria-hidden="true"
                  style="position:absolute;inset:0;background:linear-gradient(160deg,#1B222D,#0F1319)"></span>
            <span style="position:absolute;inset:0;display:grid;place-items:center;color:#8A93A3;font-size:12.5px">
                لا صورة ولا فيديو بعد — أضف أحدهما ليظهر شيء للعميل
            </span>
        @endif

        <span aria-hidden="true"
              style="position:absolute;inset:0;background:linear-gradient(to top,rgba(15,19,25,.82),rgba(15,19,25,.12) 55%,transparent)"></span>

        @if ($mainIsVideo)
            {{-- في المتجر هذا زر يُسمع الصوت. هنا يُعرض شكله فقط لأن الفيديو
                 يبدأ صامتًا في كل الأحوال. --}}
            <span style="position:absolute;top:12px;inset-inline-start:12px;z-index:3;font-size:10.5px;font-weight:800;
                         letter-spacing:.1em;background:#D2A24E;color:#17110A;padding:4px 10px;border-radius:999px">{{ $ytEmbed ? 'يوتيوب' : 'فيديو' }}</span>
            <span title="في المتجر: نقرة تُشغّل الصوت"
                  style="position:absolute;top:50%;inset-inline-start:50%;transform:translate(50%,-50%);z-index:3;
                         width:52px;height:52px;border-radius:50%;display:grid;place-items:center;
                         background:rgba(15,19,25,.62);border:1px solid rgba(241,236,225,.28);color:#F1ECE1">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                    <path d="M4 9.5h3.2L11.5 6a.9.9 0 0 1 1.5.7v10.6a.9.9 0 0 1-1.5.7L7.2 14.5H4a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1Z"/>
                    <path d="M15.7 9.9 17 8.6l2 2 2-2 1.3 1.3-2 2 2 2-1.3 1.3-2-2-2 2L15.7 14l2-2-2-2Z"/>
                </svg>
            </span>
        @endif

        <div style="position:absolute;inset-inline:22px;bottom:20px;text-align:start;z-index:3">
            @if (filled($title))
                <div style="font-family:'El Messiri',serif;font-size:24px;font-weight:600;color:#F1ECE1;text-shadow:0 2px 14px rgba(0,0,0,.6)">
                    {{ $title }}
                </div>
            @endif

            @if (filled($subtitle))
                <div style="margin-top:4px;font-size:13px;color:#C9C2B4">{{ $subtitle }}</div>
            @endif

            <span style="display:inline-block;margin-top:12px;padding:9px 20px;border-radius:8px;background:#D2A24E;color:#17110A;font-size:12.5px;font-weight:700">
                {{ $button ?: 'تسوق الآن' }}
            </span>

            @if ($href)
                <div style="margin-top:6px;font-size:11px;color:#8A93A3;direction:ltr">↳ {{ $href }}</div>
            @endif
        </div>
    </div>
</div>
