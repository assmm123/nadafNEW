@extends('layouts.nad')

@section('title', $category->name)
@section('description', $category->name . ' — ' . __('footer.tagline'))
@section('og_image', $category->imageUrl() ? url($category->imageUrl()) : url('icons/icon-512.png'))

@section('content')
    {{-- ترويسة الغرفة بنمط nad — رجوع + عنوان + عداد داخل كبسولة نحاسية --}}
    <div class="sticky top-[58px] z-40 border-b border-nad-line" style="background:rgba(17,21,28,.93);backdrop-filter:blur(14px)">
        <div class="container-x flex flex-wrap items-center gap-3.5 py-3">
            <button onclick="history.back()" class="nad-btn-ghost !rounded-full !py-2">
                <x-shop-icon name="chevron" class="h-4 w-4 rtl:rotate-180" />
                {{ __('cart.continue_shopping') }}
            </button>
            <div class="min-w-0">
                <h1 class="truncate font-display text-[clamp(20px,2.6vw,28px)] leading-tight">{{ $category->name }}</h1>
            </div>
            <span class="ms-auto flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-[rgba(210,162,78,.4)] bg-[rgba(210,162,78,.13)] px-3.5 py-1.5 text-[13px] text-nad-champ">
                <i class="h-2 w-2 shrink-0 rounded-full bg-nad-brass"></i>
                <b class="font-display text-lg">{{ $products->total() }}</b> {{ app()->getLocale() === 'en' ? 'products' : 'منتج' }}
            </span>
        </div>
    </div>

    <div class="container-x">
        {{-- شريط الفرز والفلاتر — selects بحد nad --}}
        <form method="GET" action="{{ route('category.show', $category->slug) }}" x-data class="flex flex-wrap items-center gap-2.5 pb-1 pt-4">
            @foreach (['color', 'size', 'min_price', 'max_price'] as $k)
                @if (request($k) !== null) <input type="hidden" name="{{ $k }}" value="{{ request($k) }}"> @endif
            @endforeach

            @if ($colorOptions->isNotEmpty())
                <span class="relative">
                    <select name="color" x-on:change="$el.form.submit()"
                            class="appearance-none rounded-full border border-nad-line2 bg-nad-surface py-2 pe-9 ps-4 text-[13px] font-bold text-nad-ivory outline-none transition focus:border-nad-brass">
                        <option value="">{{ __('product.color') }}: {{ app()->getLocale() === 'en' ? 'All' : 'الكل' }}</option>
                        @foreach ($colorOptions as $opt)
                            <option value="{{ $opt }}" @selected(request('color') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                    <x-shop-icon name="chevron" class="pointer-events-none absolute end-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 rotate-90 text-nad-dim rtl:-rotate-90" />
                </span>
            @endif

            @if ($sizeOptions->isNotEmpty())
                <span class="relative">
                    <select name="size" x-on:change="$el.form.submit()"
                            class="appearance-none rounded-full border border-nad-line2 bg-nad-surface py-2 pe-9 ps-4 text-[13px] font-bold text-nad-ivory outline-none transition focus:border-nad-brass">
                        <option value="">{{ __('product.size') }}: {{ app()->getLocale() === 'en' ? 'All' : 'الكل' }}</option>
                        @foreach ($sizeOptions as $opt)
                            <option value="{{ $opt }}" @selected(request('size') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                    <x-shop-icon name="chevron" class="pointer-events-none absolute end-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 rotate-90 text-nad-dim rtl:-rotate-90" />
                </span>
            @endif

            <span class="relative ms-auto">
                <select name="sort" onchange="this.form.submit()"
                        class="appearance-none rounded-full border border-nad-line2 bg-nad-surface py-2 pe-9 ps-4 text-[13px] font-bold text-nad-ivory outline-none transition focus:border-nad-brass">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('home.latest') }}</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('cart.price') }} ↑</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('cart.price') }} ↓</option>
                    <option value="bestselling" @selected(request('sort') === 'bestselling')>{{ __('home.featured') }}</option>
                </select>
                <x-shop-icon name="chevron" class="pointer-events-none absolute end-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 rotate-90 text-nad-dim rtl:-rotate-90" />
            </span>
            @if (request()->hasAny(['color', 'size', 'min_price', 'max_price', 'sort']))
                {{-- بدونها يبقى العميل عالقًا في نتيجة مصفّاة بلا وسيلة للتراجع --}}
                <a href="{{ route('category.show', $category->slug) }}"
                   class="rounded-full border border-nad-line2 px-4 py-2 text-[13px] font-bold text-nad-mut transition hover:border-nad-brass hover:text-nad-champ">
                    {{ __('common.clear_filters') }}
                </a>
            @endif
        </form>

        {{-- شبكة المنتجات — نفس بنية كروت nad-gcard في الرئيسية --}}
        @if ($products->isNotEmpty())
            <div class="grid gap-4 pb-10 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    <a href="{{ route('product.show', $product->slug) }}" class="nad-gcard group">
                        @if ($product->imageUrl())
                            <img class="ph" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
                        @else
                            <span class="absolute inset-0 flex items-center justify-center bg-nad-surface text-nad-dim">
                                <x-shop-icon name="bag" class="h-12 w-12" />
                            </span>
                        @endif
                        <span class="nad-shade"></span>
                        @php
                            $hasVariants = $product->variants->isNotEmpty();
                            $stock = $product->total_stock;
                            $lowLimit = $hasVariants ? (int) $product->variants->sum('low_stock_threshold') : 0;
                        @endphp
                        @if (! $hasVariants || $stock > 0)
                            @if ($hasVariants && $lowLimit > 0 && $stock <= $lowLimit)
                                <span class="nad-chip nad-chip-wr absolute top-3 start-3">{{ trans_choice('product.stock_left', $stock, ['count' => $stock]) }}</span>
                            @else
                                <span class="nad-chip nad-chip-ok absolute top-3 start-3">{{ __('product.in_stock') }}</span>
                            @endif
                        @else
                            <span class="nad-chip nad-chip-mut absolute top-3 start-3">{{ __('product.out_of_stock') }}</span>
                        @endif
                        @php $discount = $product->old_price_usd ? (int) round((1 - $product->price_usd / $product->old_price_usd) * 100) : 0; @endphp
                        @if ($discount > 0 && ! hide_all_prices())
                            <span class="nad-chip nad-chip-ox absolute top-3 end-3">-{{ $discount }}%</span>
                        @endif
                        <div class="nad-gmeta">
                            <div class="min-w-0">
                                <h3 class="truncate font-display text-[17px]">{{ $product->name }}</h3>
                                @if (! inquiry_mode() && ! hide_all_prices())
                                    <p class="mt-0.5 font-display text-xl leading-tight text-nad-champ">{{ fmt_price($product->price_usd) }} <span class="text-[11px] font-normal text-[#BFB8A8]">{{ session('currency', 'usd') === 'syp' ? fmt_usd($product->price_usd) : fmt_syp($product->priceSyp()) }}</span></p>
                                @endif
                            </div>
                            <span class="nad-gpill"><x-shop-icon name="chevron" class="h-4 w-4 rotate-180 rtl:rotate-0" /></span>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- ترقيم الصفحات بأزرار بحد نحاسي --}}
            @if ($products->hasPages())
                <nav class="flex flex-wrap items-center justify-center gap-2 pb-12" aria-label="Pagination">
                    @if ($products->onFirstPage())
                        <span class="grid h-10 w-10 cursor-not-allowed place-items-center rounded-full border border-nad-line2 text-nad-dim opacity-40">
                            <x-shop-icon name="chevron" class="h-4 w-4" />
                        </span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" aria-label="Previous" class="grid h-10 w-10 place-items-center rounded-full border border-nad-line2 text-nad-mut transition hover:border-nad-brass hover:text-nad-champ">
                            <x-shop-icon name="chevron" class="h-4 w-4" />
                        </a>
                    @endif

                    @foreach ($products->linkCollection() as $key => $item)
                        @if (is_null($item['url']))
                            <span class="grid h-10 min-w-10 place-items-center rounded-full border border-nad-line2 px-3 text-sm font-bold text-nad-dim">{{ $item['label'] }}</span>
                        @elseif ($item['active'])
                            <span class="grid h-10 min-w-10 place-items-center rounded-full bg-nad-brass px-3 text-sm font-extrabold text-[#171106]">{{ $item['label'] }}</span>
                        @else
                            <a href="{{ $item['url'] }}" class="grid h-10 min-w-10 place-items-center rounded-full border border-nad-line2 px-3 text-sm font-bold text-nad-mut transition hover:border-nad-brass hover:text-nad-champ">{{ $item['label'] }}</a>
                        @endif
                    @endforeach

                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" aria-label="Next" class="grid h-10 w-10 place-items-center rounded-full border border-nad-line2 text-nad-mut transition hover:border-nad-brass hover:text-nad-champ">
                            <x-shop-icon name="chevron" class="h-4 w-4 rtl:rotate-180" />
                        </a>
                    @else
                        <span class="grid h-10 w-10 cursor-not-allowed place-items-center rounded-full border border-nad-line2 text-nad-dim opacity-40">
                            <x-shop-icon name="chevron" class="h-4 w-4 rtl:rotate-180" />
                        </span>
                    @endif
                </nav>
            @endif
        @else
            {{-- الفراغ بنمط nad --}}
            <div class="flex flex-col items-center gap-3 py-16 text-center text-nad-dim">
                <x-shop-icon name="search" class="h-10 w-10 opacity-60" />
                <h3 class="font-display text-lg text-nad-mut">{{ __('search.no_results') }}</h3>
                @if (request()->hasAny(['color', 'size', 'min_price', 'max_price']))
                    <a href="{{ route('category.show', $category->slug) }}" class="nad-btn-brass">{{ __('common.clear_filters') }}</a>
                @else
                    <a href="{{ route('home') }}" class="nad-btn-ghost">{{ __('cart.continue_shopping') }}</a>
                @endif
            </div>
        @endif
    </div>
@endsection
