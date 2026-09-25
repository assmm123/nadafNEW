{{-- القسم الثاني: الأقسام — كروت بوابة بصور الأقسام وعدد المنتجات --}}
@php $isEn = app()->getLocale() === 'en'; @endphp

<div class="container-x nad-sec-block" id="categories">
    <header class="nad-sech">
        <div>
            <span class="nad-kick">Collections</span>
            <h2 style="margin-top:10px">{{ __('home.categories_title') }}</h2>
        </div>
        @isset($num)<span class="n">{{ $num }}</span>@endisset
    </header>

    @if ($homeCats->isEmpty())
        <p class="nad-empty">{{ $isEn ? 'No categories yet.' : 'لا أقسام بعد.' }}</p>
    @else
        <div class="nad-cats">
            @foreach ($homeCats as $cat)
                <a href="{{ route('category.show', $cat->slug) }}" class="nad-catcard">
                    @if ($cat->imageUrl())
                        <img src="{{ $cat->imageUrl() }}" alt="{{ $cat->name }}" loading="lazy">
                    @else
                        <span class="absolute inset-0 grid place-items-center" style="background:linear-gradient(160deg,var(--n-sf2),var(--n-bg));color:var(--n-dim)" aria-hidden="true">
                            <x-shop-icon name="store" class="h-10 w-10" />
                        </span>
                    @endif

                    <span class="veil" aria-hidden="true"></span>

                    <span class="go" aria-hidden="true">
                        <x-shop-icon name="chevron" class="h-4 w-4 rotate-180 rtl:rotate-0" />
                    </span>

                    <span class="in">
                        <b>{{ $cat->name }}</b>
                        <span>
                            @php $count = $cat->products_count ?? $cat->products->count(); @endphp
                            {{ $count }} {{ $isEn ? 'products' : 'منتج' }}
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
