{{-- إتمام الطلب — صفحة مسار الدفع (الترويسة فقط: النموذج وشريط الخطوات يرسمهما المكوّن الحي) --}}
@extends('layouts.nad')

@section('title', __('checkout.title'))

@section('content')
    <div class="container-x">

        {{-- الترويسة: kicker صغير + عنوان font-display --}}
        <div class="mt-8 flex items-center gap-3">
            <span class="text-xs font-bold tracking-widest text-nad-brass">◆ {{ __('checkout.gateway') }}</span>
            <span class="text-xs text-nad-dim">{{ __('checkout.review_line') }}</span>
        </div>
        <h1 class="mt-1 font-display text-3xl font-bold text-nad-ivory sm:text-4xl">{{ __('checkout.title') }}</h1>

        {{-- كان هنا شريط خطوات ثابت يتعارض مع الشريط الديناميكي داخل Livewire — أُزيل --}}
        {{-- غلاف nad-ocard حول نموذج الطلب الحي كاملًا --}}
        <div class="nad-ocard mx-auto my-6 max-w-6xl !rounded-[18px] border !border-nad-line p-4 sm:p-6">
            <livewire:checkout-form />
        </div>

        {{-- جملة الشحن مشتقة من الإعدادات بدل عتبة 150$ ثابتة --}}
        <p class="pb-16 text-center text-xs text-nad-dim">
            @if (\App\Models\Setting::bool('shipping_enabled', true))
                {{ __('checkout.footer_note_fee', ['fee' => fmt_usd(setting('shipping_fee_usd', 0))]) }}
            @else
                {{ __('checkout.footer_note_free') }}
            @endif
        </p>
    </div>
@endsection
