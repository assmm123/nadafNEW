@extends('layouts.nad')

@section('title', $product->name)
@section('description', \Illuminate\Support\Str::limit(strip_tags($product->description ?? ''), 150))
@section('og_type', 'product')
{{-- رابط مطلق إجباري لواجهات المشاركة (واتساب لا يقبل المسار النسبي) --}}
@section('og_image', $product->imageUrl() ? url($product->imageUrl()) : url('icons/icon-512.png'))

@section('content')
    <div class="container-x">
        @php
            $cur = session('currency', 'usd');
            $syp = $product->priceSyp();
            $mainImg = $product->imageUrl();
        @endphp

        {{-- مسار التنقل بنمط نداف — نصوص عاجية خافتة وفواصل ◆ نحاسية --}}
        <nav class="nad-bread" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('nav.home') }}</a>
            <span class="sep">◆</span>
            <a href="{{ route('category.show', $product->category->slug) }}">{{ $product->category->name }}</a>
            <span class="sep">◆</span>
            <span class="cur">{{ $product->name }}</span>
        </nav>

        {{-- شبكة صفحة المنتج — يمين: المعرض · يسار: التفاصيل --}}
        <div class="nad-pwrap" x-data="{ img: 0, zoom: null }">
            {{-- المعرض: صورة كبيرة بإطار hairline حاد + صور مصغرة --}}
            <div>
                <div class="nad-pview">
                    @if ($product->old_price_usd)
                        <span class="nad-chip nad-chip-ox nad-pbadge">-{{ round((1 - $product->price_usd / $product->old_price_usd) * 100) }}%</span>
                    @endif
                    @foreach ($product->media as $i => $media)
                        <div class="absolute inset-0 transition-opacity duration-300" :class="img === {{ $i }} ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                            @if ($media->type === 'video')
                                <video src="{{ $media->url() }}" controls playsinline preload="metadata" class="h-full w-full"></video>
                            @elseif ($media->url())
                                {{-- النقر على الصورة يفتحها بحجمها الكامل — كانت غير قابلة
                                     للنقر فلا سبيل لرؤية تفاصيل القطعة. --}}
                                <img src="{{ $media->url() }}" alt="{{ $product->name }}"
                                     @click="zoom = {{ $i }}" class="cursor-zoom-in" role="button"
                                     tabindex="0" @keydown.enter="zoom = {{ $i }}"
                                     title="{{ __('product.view_image') ?? 'عرض الصورة' }}">
                            @endif
                        </div>
                    @endforeach
                    @if (! $mainImg && $product->media->isEmpty())
                        <div class="flex h-full w-full items-center justify-center text-nad-dim">
                            <x-shop-icon name="bag" class="h-14 w-14" />
                        </div>
                    @endif
                </div>

                {{-- عارض الصورة الكامل — يُفتح بالنقر على الصورة، ويُغلق بالنقر
                     في أي مكان أو بمفتاح Escape. --}}
                <div x-show="zoom !== null" x-cloak x-transition.opacity
                     class="fixed inset-0 z-[90] flex items-center justify-center bg-black/92 p-4"
                     @click="zoom = null" @keydown.escape.window="zoom = null"
                     role="dialog" aria-modal="true">
                    <button type="button"
                            class="absolute top-5 end-5 rounded-full bg-white/10 p-2.5 text-white transition hover:bg-white/20"
                            @click="zoom = null" aria-label="{{ __('common.close') ?? 'إغلاق' }}">
                        <x-shop-icon name="x" class="h-5 w-5" />
                    </button>

                    @foreach ($product->media as $i => $media)
                        @if ($media->type !== 'video' && $media->url())
                            <img src="{{ $media->url() }}" alt="{{ $product->name }}"
                                 x-show="zoom === {{ $i }}" x-cloak
                                 class="max-h-[90vh] max-w-full rounded-lg object-contain shadow-2xl">
                        @endif
                    @endforeach
                </div>

                @if ($product->media->count() > 1)
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($product->media as $i => $media)
                            <button @click="img = {{ $i }}"
                                    class="nad-pthumb"
                                    :class="img === {{ $i }} ? 'on' : ''"
                                    aria-label="{{ __('product.description') }} {{ $i + 1 }}">
                                @if ($media->type === 'video')
                                    <span class="absolute inset-0 z-10 flex items-center justify-center bg-nad-bg/50 text-[10px] font-bold text-nad-champ">▶</span>
                                    @if ($media->url()) <video src="{{ $media->url() }}" muted preload="metadata"></video> @endif
                                @else
                                    <img src="{{ $media->url() }}" alt="">
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- التفاصيل --}}
            <div>
                <h1 class="font-display text-2xl font-bold leading-snug text-nad-ivory sm:text-3xl">{{ $product->name }}</h1>
                @if ($product->name_en)
                    <p class="mt-1 text-sm text-nad-mut">{{ $product->name_en }}</p>
                @endif

                {{-- SKU بنمط nad-ocode --}}
                @php $sku = $product->variants->first()?->sku ?: ($product->sku ?? null); @endphp
                @if ($sku)
                    <code class="nad-ocode mt-3">{{ $sku }}</code>
                @endif

                @if (inquiry_mode())
                    {{-- واتساب وحده — القناة الوحيدة التي تحمل الرسالة كاملة.
                         أما ماسنجر (m.me) والهاتف (tel:) فرابطهما لا يقبل نصًّا
                         أصلًا، فيصل المالك إلى محادثة فارغة ويسأل «أي منتج؟».
                         والرسالة تُبنى من ProductInquiry: الاسم والرمز والمواصفة
                         والسعر — إن كان المتجر يُظهره — والتوفّر ورابط مباشر. --}}
                    <div class="nad-inquiry">
                        <p>السعر عند التواصل — أرسل استفسارك على واتساب ومعه تفاصيل المنتج كاملة، وسنرد عليك بأسرع وقت</p>
                        <a href="{{ \App\Support\ProductInquiry::whatsappUrl($product) }}" target="_blank" rel="noopener"
                           class="nad-btn-brass mt-4 w-full">
                            <x-shop-icon name="whatsapp" class="h-4 w-4" />
                            {{ __('product.inquiry_whatsapp') }}
                        </a>
                        <p class="nad-inquiry-note">تُرفق مع الرسالة تلقائيًا: الاسم والرمز والسعر والتوفّر ورابط المنتج</p>
                    </div>
                @else
                    {{-- وضع none: لا أي سعر يظهر نهائيًا --}}
                    @if (! product_price_hidden($product))
                        {{-- السعر العادي — مخفي في وضع second_big أو إن أخفاه الأدمن لهذا المنتج --}}
                        @if (show_normal_price() && product_shows_unit_price($product))
                            <div class="mt-4 flex flex-wrap items-baseline gap-3">
                                <span class="font-display text-3xl font-bold text-nad-champ">
                                    {{ $cur === 'syp' ? fmt_syp($syp) : fmt_usd($product->price_usd) }}
                                </span>
                                @if ($product->old_price_usd)
                                    <del class="text-base text-nad-dim">
                                        {{ $cur === 'syp' ? fmt_syp(syp_from_usd($product->old_price_usd)) : fmt_usd($product->old_price_usd) }}
                                    </del>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-nad-mut">
                                {{ $cur === 'syp' ? fmt_usd($product->price_usd) : fmt_syp($syp) }}
                            </p>
                        @endif

                        {{-- سعر الجملة — بطاقة بحد نحاسي وعداد شمبانيا --}}
                        @if (product_shows_wholesale($product) && show_wholesale_price())
                            <div class="nad-wsbox">
                                <div class="flex items-center gap-3">
                                    <x-shop-icon name="bag" class="h-5 w-5 shrink-0 text-nad-brass" />
                                    <div>
                                        <span class="nad-wslab">
                                            {{ price_mode() === 'second_big' ? __('product.wholesale_for_quantities') : __('product.wholesale') }}
                                        </span>
                                        <span class="nad-wsval">
                                            {{ $cur === 'syp' ? fmt_syp($product->priceSyp(true)) : fmt_usd($product->wholesale_price_usd) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                @endif

                {{-- اختيار الخيارات وإضافة السلة — يُستبدل بزر واتساب في وضع الاستفسار (غلافة nad-shell) --}}
                <div class="nad-shell mt-7">
                    @if (inquiry_mode())
                        <a href="{{ \App\Support\ProductInquiry::whatsappUrl($product) }}"
                           target="_blank" rel="noopener"
                           class="nad-btn-brass w-full">
                            <x-shop-icon name="whatsapp" class="h-4 w-4" />
                            {{ __('product.order_whatsapp') }}
                        </a>
                    @else
                        <livewire:add-to-cart :product="$product" />
                    @endif
                </div>

                {{-- الاستفسار — واتساب وحده، والرسالة تحمل بيان المنتج كاملًا.
                     كان المودال يسرد كل الوسائل بروابطها المجرّدة (m.me و tel:
                     لا تقبل نصًّا أصلًا)، فيصل المالك إلى محادثة فارغة. --}}
                @if ($product->allow_inquiry && setting('inquiry_enabled_global', true) && \App\Support\ProductInquiry::hasWhatsapp())
                    <div x-data="{ open: false }" class="mt-4">
                        <button @click="open = true" class="nad-btn-ghost w-full">
                            <x-shop-icon name="phone" class="h-4 w-4" />
                            {{ __('product.inquiry') }}
                        </button>

                        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[60] flex items-end justify-center bg-nad-bg/80 p-4 backdrop-blur-sm sm:items-center" @click.self="open = false">
                            <div class="w-full max-w-sm rounded-2xl border border-nad-line bg-nad-surface p-6 shadow-2xl">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="font-display text-lg font-bold text-nad-ivory">{{ __('product.inquiry_title') }}</h3>
                                    <button @click="open = false" class="rounded-lg p-1 text-nad-mut hover:text-nad-champ"><x-shop-icon name="x" class="h-5 w-5" /></button>
                                </div>
                                <p class="mb-4 text-sm text-nad-mut">{{ __('product.inquiry_desc') }}</p>

                                <a href="{{ \App\Support\ProductInquiry::whatsappUrl($product) }}" target="_blank" rel="noopener"
                                   class="nad-btn-brass w-full">
                                    <x-shop-icon name="whatsapp" class="h-4 w-4" />
                                    {{ __('product.inquiry_whatsapp') }}
                                </a>

                                <p class="mt-3 text-center text-[11px] leading-5 text-nad-dim">
                                    تُرفق مع الرسالة تلقائيًا: اسم المنتج ورمزه وسعره وتوفّره ورابطه المباشر
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- الوصف داخل nad-ocard --}}
                @if ($product->description)
                    <div class="nad-ocard mt-6 p-5">
                        <h2 class="mb-2 font-display text-base font-bold text-nad-champ">{{ __('product.description') }}</h2>
                        <p class="whitespace-pre-line text-sm leading-8 text-nad-mut">{{ $product->description }}</p>
                    </div>
                @endif

                {{-- شارات ثقة صغيرة --}}
                <div class="nad-trust">
                    <span class="nad-tcell"><x-shop-icon name="truck" /> <b>{{ __('product.trust_delivery') }}</b> {{ __('product.trust_delivery_note') }}</span>
                    <span class="nad-tcell"><x-shop-icon name="check" /> <b>{{ __('product.trust_guarantee') }}</b> {{ __('product.trust_guarantee_note') }}</span>
                    <span class="nad-tcell"><x-shop-icon name="phone" /> <b>{{ __('product.trust_support') }}</b> {{ __('product.trust_support_note') }}</span>
                </div>
            </div>
        </div>

        {{-- منتجات مشابهة — كروت nad-gcard بنفس بنية كروت الرئيسية --}}
        @if ($related->isNotEmpty())
            <section>
                <header class="nad-sec nad-sec-sm">
                    <h2><span class="dia">◆</span>{{ __('product.related') }}</h2>
                </header>
                <div class="nad-grid">
                    @foreach ($related as $rel)
                        @php
                            $rimg = $rel->imageUrl();
                            $rdisc = $rel->old_price_usd ? round((1 - $rel->price_usd / $rel->old_price_usd) * 100) : 0;
                        @endphp
                        <a href="{{ route('product.show', $rel->slug) }}" class="nad-gcard" style="aspect-ratio:4/3">
                            @if ($rimg)
                                <img class="ph" src="{{ $rimg }}" alt="{{ $rel->name }}" loading="lazy">
                            @else
                                <span class="nad-shade"></span>
                                <span class="nad-gicon"><x-shop-icon name="bag" /></span>
                            @endif
                            <span class="nad-shade"></span>
                            @if ($rdisc > 0)
                                <span class="nad-chip nad-chip-ox" style="position:absolute;top:10px;inset-inline-start:10px">-{{ $rdisc }}%</span>
                            @endif
                            @if (! $rel->inStock())
                                <span class="nad-chip nad-chip-mut" style="position:absolute;top:10px;inset-inline-end:10px">{{ __('product.out_of_stock') }}</span>
                            @endif
                            <div class="nad-gmeta">
                                <div class="min-w-0">
                                    <h3 class="line-clamp-1 !text-base">{{ $rel->name }}</h3>
                                    {{-- كان يُرسم كبسولة <code> فارغة عند غياب رقم القطعة --}}
                                    @if ($rel->variants->first()?->sku)
                                        <code class="nad-ocode mt-1">{{ $rel->variants->first()->sku }}</code>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <b class="nad-price block !text-lg">{{ product_price_hidden($rel) ? '' : ($cur === 'syp' ? fmt_syp($rel->priceSyp()) : fmt_usd($rel->price_usd)) }}</b>
                                    @if ($rel->old_price_usd && ! product_price_hidden($rel))
                                        <del class="text-[10px] text-nad-dim">{{ $cur === 'syp' ? fmt_syp(syp_from_usd($rel->old_price_usd)) : fmt_usd($rel->old_price_usd) }}</del>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
