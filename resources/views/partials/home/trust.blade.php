{{-- القسم الرابع: شريط الثقة — أربع بطاقات، نصوصها من الإعدادات --}}
@php
    $isEn = app()->getLocale() === 'en';
    $icons = ['truck', 'check', 'banknote', 'store'];
@endphp

<div class="container-x nad-sec-block" id="trust">
    <header class="nad-sech">
        <div>
            <span class="nad-kick">Why Nadaf</span>
            <h2 style="margin-top:10px">{{ $isEn ? 'Why Nadaf' : 'لماذا نداف' }}</h2>
        </div>
        @isset($num)<span class="n">{{ $num }}</span>@endisset
    </header>

    <div class="nad-trustgrid">
        @foreach ([1, 2, 3, 4] as $n)
            @php
                $title = setting("trust_{$n}_title");
                $text = setting("trust_{$n}_text");
            @endphp

            @if ($title)
                <div class="nad-tcard">
                    <span class="ic" aria-hidden="true">
                        <x-shop-icon :name="$icons[$n - 1]" class="h-5 w-5" />
                    </span>
                    <b>{{ $title }}</b>
                    @if ($text)
                        <p>{{ $text }}</p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
