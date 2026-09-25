{{-- كرت منتج للشبكات --}}
@props(['product'])

@php
    $cur = session('currency', 'usd');
    $syp = $product->priceSyp();
    $img = $product->imageUrl();
    $discount = $product->old_price_usd ? (int) round((1 - $product->price_usd / $product->old_price_usd) * 100) : 0;
    $url = route('product.show', $product->slug);
@endphp

{{-- ليس رابطًا واحدًا: الرابط الخارجي كان يحتوي رابط واتساب داخله = <a> داخل <a> (HTML غير صالح ويكسر النقر على الجوال) --}}
<div class="group card overflow-hidden transition hover:shadow-md">
    <a href="{{ $url }}" class="relative block aspect-square overflow-hidden bg-cream">
        @if ($img)
            <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <span class="flex h-full w-full items-center justify-center text-navy-200" aria-hidden="true">
                <x-shop-icon name="bag" class="h-12 w-12" />
            </span>
        @endif

        @if ($discount > 0)
            {{-- red-500 على الأبيض كان 3.76:1 (راسب) — red-700 يعطي 6.47:1 --}}
            <span class="badge absolute top-2 bg-red-700 text-white start-2">-{{ $discount }}%</span>
        @endif

        @if (! $product->inStock())
            <span class="absolute inset-0 flex items-center justify-center bg-white/70">
                <span class="badge bg-navy-900 text-white">{{ __('product.out_of_stock') }}</span>
            </span>
        @endif
    </a>

    <div class="p-3">
        <h3 class="line-clamp-1 text-[15px] font-bold">
            <a href="{{ $url }}" class="transition hover:text-gold-600">{{ $product->name }}</a>
        </h3>
        @if (hide_all_prices())
            {{-- الوضع العام «إخفاء كل الأسعار»: لا سعر ولا بديل --}}
        @elseif (inquiry_mode() || $product->hidesPrice())
            {{-- إخفاء عام بالاستفسار، أو إخفاء خاص بهذا المنتج --}}
            @if ($product->allow_inquiry)
                <a href="{{ \App\Support\ProductInquiry::whatsappUrl($product) }}"
                   target="_blank" rel="noopener"
                   class="mt-2 inline-flex items-center gap-1.5 text-xs font-extrabold text-green-700 transition hover:text-green-800">
                    <x-shop-icon name="whatsapp" class="h-4 w-4" />
                    {{ __('product.inquiry_whatsapp') }}
                </a>
            @endif
        @else
            @if (product_shows_unit_price($product))
                <div class="mt-1.5 flex items-baseline gap-2">
                    <span class="text-lg font-extrabold text-gold-600">
                        {{ $cur === 'syp' ? fmt_syp($syp) : fmt_usd($product->price_usd) }}
                    </span>
                    @if ($product->old_price_usd)
                        {{-- gray-400 على الأبيض كان 2.54:1 (راسب فادح) — gray-500 يعطي 4.83:1 --}}
                        <del class="text-xs text-gray-500">
                            {{ $cur === 'syp' ? fmt_syp(syp_from_usd($product->old_price_usd)) : fmt_usd($product->old_price_usd) }}
                        </del>
                    @endif
                </div>
                <div class="mt-0.5 text-[11px] text-gray-500">
                    {{ $cur === 'syp' ? fmt_usd($product->price_usd) : fmt_syp($syp) }}
                </div>
            @endif
        @endif
    </div>
</div>
