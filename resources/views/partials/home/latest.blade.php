{{-- القسم الثالث: أحدث المنتجات — كروت تحتوي كل المواصفات داخلها --}}
@php $isEn = app()->getLocale() === 'en'; @endphp

<div class="container-x nad-sec-block" id="latest">
    <header class="nad-sech">
        <div>
            <span class="nad-kick">New Arrivals</span>
            <h2 style="margin-top:10px">{{ __('home.latest') }}</h2>
        </div>
        @isset($num)<span class="n">{{ $num }}</span>@endisset
    </header>

    @if ($latestProducts->isEmpty())
        <p class="nad-empty">{{ $isEn ? 'No products yet.' : 'لا منتجات بعد.' }}</p>
    @else
        <div class="nad-pgrid">
            @foreach ($latestProducts as $product)
                @include('partials.nad-product-card', ['product' => $product])
            @endforeach
        </div>
    @endif
</div>
