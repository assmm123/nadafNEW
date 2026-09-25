{{--
    القسم الأول: السلايدر + تعريف المنصة
    السلايدر يقبل صورًا وفيديو (video_path في جدول slides)، وارتفاعه من إعداد
    home_hero_height (نسبة من ارتفاع الشاشة). عمود التعريف بجانبه بنفس الارتفاع.

    الصوت: الفيديو يبدأ صامتًا لأن كروم وسفاري يحجبان التشغيل التلقائي بالصوت،
    فلا سبيل إلى كسر ذلك من الكود. والبديل زر واحد واضح يشغّل الصوت بنقرة —
    والنقرة إيماءة مستخدم يسمح بها المتصفح. والنبض حول الزر يستمر ما دام
    صامتًا ليجذب النقرة، ويسكن عند التشغيل.
--}}
@php
    $slH = max(38, min(90, (int) setting('home_hero_height', 56)));
    $total = max($slides->count(), 1);
    $isEn = app()->getLocale() === 'en';

    // مدة كل شريحة على حدة — لا ٦ ثوانٍ للجميع
    $durations = $slides->map(fn ($s) => $s->duration())->values()->all();

    // زر الصوت بلا معنى إن لم يكن في السلايدر فيديو أصلًا
    $hasVideo = $slides->contains(fn ($s) => $s->hasVideo());
@endphp

<section class="container-x nad-hero" style="--nad-sl-h:{{ $slH }}vh">
    <div class="nad-sl"
         x-data="{
                    i: 0,
                    total: {{ $total }},
                    durations: @js($durations),
                    sound: false,
                    t: null,
                    start() {
                        this.stop();
                        if (this.total < 2) return;
                        const ms = (this.durations[this.i] || 6) * 1000;
                        this.t = setTimeout(() => {
                            this.i = (this.i + 1) % this.total;
                            this.start();
                        }, ms);
                    },
                    stop() { clearTimeout(this.t); this.t = null },
                    go(n) { this.i = n; this.start() },
                    toggleSound() { this.sound = ! this.sound },

                    {{-- الشرائح كلها تعمل في آن واحد (autoplay loop)، فإن أُطلق
                         الصوت للجميع تكلّم الفيديوهات المخفيّة أيضًا. فلا يُفتح
                         الصوت إلا للشريحة المعروضة وحدها. --}}
                    applySound() {
                        this.$root.querySelectorAll('video').forEach((v) => {
                            const want = this.sound && Number(v.dataset.slide) === this.i;
                            v.muted = ! want;
                            if (want) v.play().catch(() => {});
                        });
                    }
                 }"
         x-init="start()"
         x-effect="applySound()"
         @mouseenter="stop()" @mouseleave="start()" @focusin="stop()" @focusout="start()">

        @forelse ($slides as $n => $slide)
            <div class="nad-sl-item {{ $n === 0 ? 'on' : '' }} {{ $slide->hasVideo() ? 'is-video' : '' }}"
                 :class="i === {{ $n }} ? 'on' : ''">
                @if ($slide->hasVideo())
                    {{-- الصورة تُعرض قبل تحميل الفيديو فلا يرى العميل شريحة سوداء --}}
                    <video src="{{ $slide->videoUrl() }}"
                           data-slide="{{ $n }}"
                           @if ($slide->imageUrl()) poster="{{ $slide->imageUrl() }}" @endif
                           autoplay muted loop playsinline></video>
                @elseif ($slide->imageUrl())
                    <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->title ?? '' }}"
                         loading="{{ $n === 0 ? 'eager' : 'lazy' }}">
                @endif

                <span class="nad-sl-veil" aria-hidden="true"></span>

                @if ($slide->hasVideo())
                    <span class="nad-sl-tag">{{ $isEn ? 'Video' : 'فيديو' }}</span>

                    {{-- زر الصوت في موضع شارة التشغيل المعتاد: أظهر ما يقع عليه
                         النظر وأقربه إلى الإبهام. ولا وجود لشارة تشغيل وهمية،
                         فالفيديو يعمل أصلًا ولا ينتظر نقرة. --}}
                    <button type="button" class="nad-sl-sound"
                            @click="toggleSound()"
                            :class="sound && 'on'"
                            :aria-pressed="sound ? 'true' : 'false'"
                            :aria-label="sound ? @js($isEn ? 'Mute video' : 'اكتم صوت الفيديو')
                                               : @js($isEn ? 'Unmute video' : 'شغّل صوت الفيديو')">
                        <span class="nad-snd-on"><x-shop-icon name="volume-on" class="h-6 w-6" /></span>
                        <span class="nad-snd-off"><x-shop-icon name="volume-off" class="h-6 w-6" /></span>
                    </button>
                @endif

                <span class="nad-sl-meta">{{ str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $slides->count(), 2, '0', STR_PAD_LEFT) }}</span>

                <div class="nad-sl-cap">
                    @if ($slide->title)
                        <h2>{{ $slide->title }}</h2>
                    @endif
                    @if ($slide->subtitle)
                        <p class="nad-sl-sub">{{ $slide->subtitle }}</p>
                    @endif
                    {{-- الزر على كل شريحة: رابطها إن وُجد، وإلا الرابط الافتراضي
                         من الإعدادات. لا شريحة بلا مدخل إلى البيع. --}}
                    <a href="{{ $slide->linkUrl() }}" class="nad-btn-brass nad-btn-sm">{{ $slide->buttonLabel() }}</a>
                </div>
            </div>
        @empty
            {{-- بلا شرائح: تدرّج من الهوية بدل صورة خارجية --}}
            <div class="nad-sl-item on">
                <span aria-hidden="true" style="position:absolute;inset:0;background:radial-gradient(900px 420px at 78% 12%,color-mix(in srgb,var(--n-brass) 26%,transparent),transparent 62%),linear-gradient(160deg,var(--n-sf2),var(--n-bg))"></span>
                <span class="nad-sl-veil" aria-hidden="true"></span>
                <div class="nad-sl-cap">
                    <h2>{{ __('home.hero_title') }}</h2>
                    <p>{{ __('footer.tagline') }}</p>
                    <a href="#categories" class="nad-btn-brass">{{ __('home.shop_now') }}</a>
                </div>
            </div>
        @endforelse

        @if ($slides->count() > 1)
            <div class="nad-sl-nav">
                <div class="nad-sl-dots">
                    @foreach ($slides as $n => $slide)
                        {{-- go() لا الإسناد المباشر: حتى تُعاد جدولة المؤقّت من جديد --}}
                        <button type="button" @click="go({{ $n }})" :class="i === {{ $n }} ? 'on' : ''"
                                aria-label="{{ __('home.slide') }} {{ $n + 1 }}"></button>
                    @endforeach
                </div>
                <button type="button" class="nad-sl-arrow" @click="go((i - 1 + total) % total)"
                        aria-label="{{ $isEn ? 'Previous' : 'السابق' }}">
                    <x-shop-icon name="chevron" class="h-4 w-4 rotate-180 rtl:rotate-0" />
                </button>
                <button type="button" class="nad-sl-arrow" @click="go((i + 1) % total)"
                        aria-label="{{ $isEn ? 'Next' : 'التالي' }}">
                    <x-shop-icon name="chevron" class="h-4 w-4" />
                </button>
            </div>
        @endif
    </div>

    {{-- تعريف المنصة — كل نصه من الإعدادات --}}
    <div class="nad-about">
        @if (setting('about_kicker'))
            <span class="nad-kick">{{ setting('about_kicker') }}</span>
        @endif

        <h1>
            {{ setting('about_title', 'لا نبيع كرافات') }}
            @if (setting('about_title_accent'))
                <span>{{ setting('about_title_accent') }}</span>
            @endif
        </h1>

        @if (setting('about_body'))
            <p>{{ setting('about_body') }}</p>
        @endif

        @php
            $stats = collect([1, 2, 3])
                ->map(fn ($n) => [
                    'value' => setting("about_stat_{$n}_value"),
                    'label' => setting("about_stat_{$n}_label"),
                ])
                ->filter(fn ($s) => filled($s['value']));
        @endphp

        @if ($stats->isNotEmpty())
            <div class="nad-hstats">
                @foreach ($stats as $s)
                    <div><b>{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></div>
                @endforeach
            </div>
        @endif

        @if (setting('about_cta_1_label') || setting('about_cta_2_label'))
            <div class="nad-about-cta">
                @if (setting('about_cta_1_label'))
                    <a href="{{ setting('about_cta_1_link', '#categories') }}" class="nad-btn-brass">
                        {{ setting('about_cta_1_label') }}
                    </a>
                @endif
                @if (setting('about_cta_2_label'))
                    <a href="{{ setting('about_cta_2_link', '#') }}" class="nad-btn-ghost">
                        {{ setting('about_cta_2_label') }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
