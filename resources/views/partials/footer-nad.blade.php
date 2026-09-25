<footer class="mt-16 border-t border-nad-line bg-nad-surface">
    <div class="container-x grid gap-10 py-12 md:grid-cols-3">
        <div>
            @php
                // نفس شعار المالك المستخدم في الهيدر — فحقل الإعدادات يَعِد
                // بأنه «يُستخدم في الهيدر والفوتر» معًا.
                $footerLogoPath = setting('logo_path');
                $footerLogo = $footerLogoPath
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($footerLogoPath)
                    : null;
            @endphp

            @if ($footerLogo)
                <img src="{{ $footerLogo }}" alt="{{ setting('store_name_ar', 'نداف') }}"
                     class="h-14 w-auto max-w-[220px] object-contain">
            @else
                <div class="flex items-baseline gap-2">
                    <span class="font-display text-2xl font-bold text-nad-ivory">{{ setting('store_name_ar', 'نداف') }}</span>
                    <span class="text-[9px] text-nad-brass">◆</span>
                </div>
            @endif

            <p class="mt-4 max-w-xs text-sm leading-6 text-nad-mut">{{ __('footer.tagline') }}</p>
        </div>

        <div>
            <h3 class="mb-4 font-display text-base font-bold text-nad-champ">{{ __('footer.quick_links') }}</h3>
            <ul class="space-y-2.5 text-sm text-nad-mut">
                <li><a href="{{ route('home') }}" class="transition hover:text-nad-champ">{{ __('nav.home') }}</a></li>
                <li><a href="{{ route('contact') }}" class="transition hover:text-nad-champ">{{ __('nav.contact') }}</a></li>
                @foreach ($footerPages as $page)
                    <li><a href="{{ route('page.show', $page->slug) }}" class="transition hover:text-nad-champ">{{ $page->title }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="mb-4 font-display text-base font-bold text-nad-champ">{{ __('footer.contact_us') ?? 'تواصل معنا' }}</h3>
            @if ($contactMethods->isNotEmpty())
                <ul class="space-y-2.5 text-sm text-nad-mut">
                    @foreach ($contactMethods->take(4) as $cm)
                        <li>
                            <a href="{{ $cm->link() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2.5 transition hover:text-nad-champ">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-nad-bg text-nad-brass">
                                    <x-shop-icon name="{{ \App\Models\CommunicationMethod::TYPES[$cm->type]['icon'] ?? 'globe' }}" class="h-3.5 w-3.5" />
                                </span>
                                {{ $cm->label ?? $cm->typeLabel() }} — <span dir="ltr" class="text-[11px]">{{ $cm->value }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-nad-mut">{{ setting('store_phone') }}</p>
            @endif
        </div>
    </div>

    <div class="border-t border-nad-line2">
        <div class="container-x flex flex-col items-center justify-between gap-2 py-4 text-xs text-nad-dim sm:flex-row">
            <p>© {{ date('Y') }} {{ app()->getLocale() === 'en' ? setting('store_name_en', 'NADAF') : setting('store_name_ar', 'نداف') }} — {{ __('footer.rights') }}</p>
            <p class="flex items-center gap-1.5">{{ __('home.shop_now') }} <span class="text-nad-brass">◆</span></p>
        </div>
    </div>

    {{-- بيانات المطوّر — أزرق شفاف أسفل الموقع، بلون هادئ لا ينافس محتوى المتجر --}}
    <div class="border-t" style="border-color: rgba(96,165,250,.16)">
        <div class="container-x flex items-center justify-center gap-4 py-4 text-xs">
            <span class="font-bold tracking-[.14em]" style="color: rgba(96,165,250,.85)">ASSM MSSTO</span>

            {{-- أيقونات فقط — بلا رقم ولا بريد مكتوبين على الشاشة.
                 والرقم والبريد يبقيان في الوسم وحدهما (aria-label وtitle)
                 للوصولية ولقارئ الشاشة، لا للعرض. --}}
            <a href="https://wa.me/963930322406" target="_blank" rel="noopener"
               class="inline-flex h-8 w-8 items-center justify-center rounded-full border transition hover:opacity-100"
               style="color: rgba(96,165,250,.78);border-color: rgba(96,165,250,.3)"
               aria-label="تواصل مع المطوّر على واتساب" title="واتساب المطوّر">
                <x-shop-icon name="whatsapp" class="h-4 w-4" />
            </a>

            <a href="mailto:assmm944@gmail.com"
               class="inline-flex h-8 w-8 items-center justify-center rounded-full border transition hover:opacity-100"
               style="color: rgba(96,165,250,.78);border-color: rgba(96,165,250,.3)"
               aria-label="راسل المطوّر بالبريد الإلكتروني" title="بريد المطوّر">
                <x-shop-icon name="envelope" class="h-4 w-4" />
            </a>
        </div>
    </div>
</footer>
