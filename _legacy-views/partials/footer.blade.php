<footer class="mt-16 bg-navy-900 text-white">
    <div class="container-x grid gap-10 py-12 md:grid-cols-3">
        <div>
            @php
                // نفس شعار المالك المستخدم في الهيدر — فحقل الإعدادات يَعِد
                // بأنه «يُستخدم في الهيدر والفوتر» معًا.
                $appFooterLogoPath = setting('logo_path');
                $appFooterLogo = $appFooterLogoPath
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($appFooterLogoPath)
                    : null;
            @endphp

            @if ($appFooterLogo)
                <img src="{{ $appFooterLogo }}" alt="{{ setting('store_name_ar', 'نداف') }}"
                     class="h-14 w-auto max-w-[220px] object-contain">
            @else
                <div class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo-shield.svg') }}" alt="نداف" class="h-14 w-auto" width="46" height="56">
                    <div class="leading-none">
                        <span class="text-xl font-extrabold tracking-wide text-white">{{ setting('store_name_ar', 'نداف') }}</span>
                        <p class="mt-1 text-[10px] font-bold tracking-widest text-gold-400">أناقة تليق بك</p>
                    </div>
                </div>
            @endif

            <p class="mt-4 max-w-xs text-sm leading-6 opacity-70">{{ __('footer.tagline') }}</p>
        </div>

        <div>
            <h3 class="mb-4 font-extrabold text-gold-400">{{ __('footer.quick_links') }}</h3>
            <ul class="space-y-2.5 text-sm opacity-80">
                <li><a href="{{ route('home') }}" class="hover:text-gold-400">{{ __('nav.home') }}</a></li>
                <li><a href="{{ route('cart.index') }}" class="hover:text-gold-400">{{ __('nav.cart') }}</a></li>
                @foreach ($footerPages as $page)
                    <li><a href="{{ route('page.show', $page->slug) }}" class="hover:text-gold-400">{{ $page->title }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="mb-4 font-extrabold text-gold-400">{{ __('footer.contact_us') }}</h3>
            <ul class="space-y-2.5 text-sm opacity-80">
                @foreach ($contactMethods as $method)
                    <li>
                        <a href="{{ $method->link() }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 hover:text-gold-400">
                            <x-shop-icon name="{{ \App\Models\CommunicationMethod::TYPES[$method->type]['icon'] ?? 'globe' }}" class="h-4.5 w-4.5 shrink-0 text-gold-500" />
                            <span>{{ $method->label ?? $method->typeLabel() }}</span>
                            <span class="opacity-60" dir="ltr">{{ $method->value }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-x flex flex-col items-center justify-between gap-2 py-4 text-xs opacity-60 sm:flex-row">
            <p>© {{ date('Y') }} {{ app()->getLocale() === 'en' ? setting('store_name_en', 'NADAF') : setting('store_name_ar', 'نداف') }} — {{ __('footer.rights') }}</p>
            <p class="flex items-center gap-1.5">{{ __('home.shop_now') }} <span class="text-gold-500">•</span> {{ __('footer.rights') }}</p>
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
