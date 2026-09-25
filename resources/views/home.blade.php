@extends('layouts.nad')

@section('title', __('nav.home'))
@section('description', __('footer.tagline'))

@section('content')
    @php
        $latestProducts = $latest ?? ($products ?? \App\Models\Product::active()->with(['media', 'variants'])->latest()->take(4)->get());
        // قائمة المتحكم أولًا: تحمل products_count مقيَّدًا بالمنتجات النشطة
        $homeCats = $categories ?? $headerCategories;
        $slides = $slides ?? collect();

        // ترتيب الأقسام من الإعدادات (app/Support/HomeSections.php).
        // الأرقام التسلسلية تُحسب من الترتيب الفعلي، فتبقى صحيحة بعد أي إعادة ترتيب.
        $order = \App\Support\HomeSections::active();
        $numbered = array_values(array_diff($order, ['hero']));
    @endphp

    @foreach ($order as $section)
        @includeIf("partials.home.{$section}", [
            'slides' => $slides,
            'latestProducts' => $latestProducts,
            'homeCats' => $homeCats,
            'num' => $section === 'hero'
                ? null
                : str_pad((string) (array_search($section, $numbered) + 1), 2, '0', STR_PAD_LEFT),
        ])
    @endforeach
@endsection
