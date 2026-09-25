{{-- كرت منتج — كل المواصفات داخل الكرت: الاسم · المتغيرات · السعر · الحالة --}}
@props(['product'])

@php
    $cur = session('currency', 'usd');
    $url = route('product.show', $product->slug);

    // العلاقات محمّلة مسبقًا في الرئيسية والقسم — لا استعلام إضافي
    $variants = $product->relationLoaded('variants') ? $product->variants : collect();
    $hasVariants = $variants->isNotEmpty();

    // مصدر واحد للمخزون — نفس ما تعرضه اللوحة، فلا يتناقضان
    $stockInfo = $product->stockInfo();
    $stock = $stockInfo['quantity'];

    // الألوان والمقاسات تُخفى كليًا إن اختار الأدمن ذلك لهذا المنتج
    $showsColors = product_shows_colors($product);
    $colors = $showsColors ? $variants->pluck('color')->filter()->unique()->values() : collect();
    $sizes = $showsColors ? $variants->pluck('size')->filter()->unique()->values() : collect();

    $discount = $product->old_price_usd && (float) $product->old_price_usd > 0
        ? (int) round((1 - $product->price_usd / $product->old_price_usd) * 100)
        : 0;

    $hidePrices = hide_all_prices();
    $inquiry = inquiry_mode() || $product->hidesPrice();
    $showsUnit = product_shows_unit_price($product);
@endphp

<article class="nad-pcard">
    <a href="{{ $url }}" class="shot" aria-label="{{ $product->name }}">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <span class="empty"><x-shop-icon name="bag" class="h-11 w-11" /></span>
        @endif

        @if ($discount > 0 && ! $hidePrices)
            <span class="tag sale">-{{ $discount }}%</span>
        @elseif ($hasVariants && $stock <= 0)
            <span class="tag">{{ __('product.out_of_stock') }}</span>
        @endif
    </a>

    <div class="body">
        <h3><a href="{{ $url }}">{{ $product->name }}</a></h3>

        {{-- المواصفات داخل الكرت: الألوان والمقاسات من متغيرات المنتج فعليًا --}}
        @if ($colors->isNotEmpty() || $sizes->isNotEmpty())
            <p class="spec">
                @if ($colors->isNotEmpty())
                    <span>{{ $colors->take(3)->implode(' · ') }}@if ($colors->count() > 3) +{{ $colors->count() - 3 }}@endif</span>
                @endif
                @if ($colors->isNotEmpty() && $sizes->isNotEmpty())
                    <i aria-hidden="true"></i>
                @endif
                @if ($sizes->isNotEmpty())
                    <span>{{ $sizes->take(2)->implode(' · ') }}</span>
                @endif
            </p>
        @elseif ($product->internal_code)
            {{-- بلا متغيرات: الكود الداخلي هو المواصفة الوحيدة المتاحة --}}
            <p class="spec"><span style="direction:ltr;font-family:ui-monospace,monospace">{{ $product->internal_code }}</span></p>
        @endif

        <div class="foot">
            @if ($hidePrices)
                <div class="p"><b>—</b></div>
            @elseif ($inquiry)
                <a href="{{ \App\Support\ProductInquiry::whatsappUrl($product) }}"
                   target="_blank" rel="noopener" class="nad-chip nad-chip-br chip">
                    {{ __('product.inquiry_whatsapp') }}
                </a>
            @elseif ($showsUnit)
                <div class="p">
                    <b>
                        {{ fmt_price($product->price_usd) }}
                        @if ($product->old_price_usd)
                            <s>{{ $cur === 'syp' ? fmt_syp(syp_from_usd($product->old_price_usd)) : fmt_usd($product->old_price_usd) }}</s>
                        @endif
                    </b>
                    <span>{{ $cur === 'syp' ? fmt_usd($product->price_usd) : fmt_syp($product->priceSyp()) }}</span>
                </div>
            @endif

            {{-- حالة المخزون من stockInfo() — نفس مصدر اللوحة --}}
            @if ($stockInfo['kind'] === 'unlimited')
                <span class="nad-chip nad-chip-ok chip">{{ __('product.in_stock') }}</span>
            @elseif ($stockInfo['kind'] === 'out')
                <span class="nad-chip nad-chip-mut chip">{{ __('product.out_of_stock') }}</span>
            @elseif ($stockInfo['kind'] === 'low')
                <span class="nad-chip nad-chip-wr chip">{{ trans_choice('product.stock_left', $stock, ['count' => $stock]) }}</span>
            @else
                <span class="nad-chip nad-chip-ok chip">{{ __('product.in_stock') }}</span>
            @endif
        </div>
    </div>
</article>
