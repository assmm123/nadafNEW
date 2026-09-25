@extends('layouts.nad')

@section('title', __('checkout.order_created_title'))

@section('content')
    <div class="container-x mx-auto max-w-2xl pb-16">

        <div class="nad-donebox">
            {{-- علامة الصح النابضة بنمط nad.html --}}
            <div class="nad-pulse">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5.5 12.5 4 4 9-9.5"/></svg>
            </div>

            <h1 class="mt-5 font-display text-2xl font-bold text-nad-ivory sm:text-[27px]">
                شكرًا لك يا {{ $order->customerName() }}
            </h1>
            <p class="mt-2 text-sm text-nad-mut">{{ __('checkout.order_created_title') }} — طلبك قيد المراجعة وسيتم التواصل معك في أسرع وقت.</p>

            {{-- كود الطلب داخل إطار نحاسي مزدوج (حد + outline offset) بخط monospace --}}
            <p class="mt-5 text-xs tracking-widest text-nad-dim">{{ __('checkout.your_code') }}</p>
            <div class="nad-ocodeframe">{{ $order->order_code }}</div>
            <p class="mt-2 text-xs text-nad-dim">{{ __('checkout.code_note') }}</p>

            {{-- إرسال الطلب على واتساب — يظهر دائمًا، ويُفتح تلقائيًّا إن جاء
                 العميل من زر «إتمام الطلب + إرسال واتساب». والطلب مُنشأ سلفًا
                 فلا يضيع إن مُنع الفتح التلقائي أو أُغلق واتساب. --}}
            @php $waUrl = \App\Support\OrderWhatsapp::url($order); @endphp
            <a href="{{ $waUrl }}" target="_blank" rel="noopener" id="wa-send-order"
               class="nad-btn-ghost mx-auto mt-5 flex w-full max-w-sm items-center justify-center gap-2 !border-[#25D366]/60 !text-[#25D366] hover:!bg-[#25D366]/10">
                <x-shop-icon name="whatsapp" class="h-4 w-4" />
                {{ __('checkout.send_order_whatsapp') }}
            </a>
            <p class="mt-2 text-center text-[11px] text-nad-dim">
                تُرسل الرسالة بتفاصيل الطلب كاملة — كل منتج في مقطع منفصل.
            </p>

            {{-- ملخص الإجماليات $/ل.س --}}
            <div class="nad-ocard mx-auto mt-6 max-w-sm p-5">
                <div class="flex items-center justify-between gap-3 text-sm text-nad-mut">
                    <span>{{ __('cart.grand_total') }}</span>
                    <b class="nad-price">{{ fmt_usd($order->total_usd) }}</b>
                </div>
                <div class="mt-1 flex items-center justify-end text-xs text-nad-dim" dir="ltr">
                    <span>{{ fmt_syp($order->total_syp) }}</span>
                </div>
            </div>

            {{-- أزرار: تتبع طلبي + متابعة التسوق --}}
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                {{-- «تتبع طلبي» يقود إلى الحساب — ولا حساب للضيف. فيُخفى عنه
                     بدل أن يفتح صفحة دخول لا يريدها. --}}
                @auth
                    <a href="{{ route('account.order', $order->order_code) }}" class="nad-btn-brass">تتبع طلبي</a>
                @endauth
                <a href="{{ route('home') }}" class="nad-btn-ghost">{{ __('cart.continue_shopping') }}</a>
            </div>
        </div>

        {{-- بيانات إضافية للطلب إن وجدت (وسيلة الدفع / الحوالة) بنمط nad-ocard --}}
        @if ($order->payment_method_id || $order->payment_reference || $order->shipping_method)
            <div class="nad-ocard mx-auto mb-10 max-w-2xl p-5">
                <h2 class="font-display text-lg text-nad-champ">تفاصيل الطلب</h2>
                <div class="nad-mbox mt-3">
                    @if ($order->paymentMethod)
                        <div><span>وسيلة الدفع</span><b>{{ $order->paymentMethod->name }}</b></div>
                    @endif
                    @if ($order->payment_reference)
                        <div><span>رقم الحوالة</span><b dir="ltr">{{ $order->payment_reference }}</b></div>
                    @endif
                    @if ($order->payment_sender_name)
                        <div><span>اسم المرسل</span><b>{{ $order->payment_sender_name }}</b></div>
                    @endif
                    @if ($order->shipping_method)
                        <div><span>طريقة الاستلام</span><b>{{ $order->shipping_method === 'pickup' ? __('checkout.pickup') : __('checkout.local') }}</b></div>
                    @endif
                    @if ($order->city || $order->shipping_address)
                        <div><span>العنوان</span><b>{{ trim(($order->city ? $order->city.' — ' : '').$order->shipping_address) }}</b></div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if (request()->boolean('wa'))
        {{-- جاء العميل من زر «إتمام الطلب + إرسال واتساب» — نفتح واتساب تلقائيًّا.
             والطلب مُنشأ سلفًا، فإن منع المتصفح الفتح التلقائي (مانع النوافذ
             المنبثقة) بقي الزر أعلاه بنقرة واحدة. --}}
        <script>
            (function () {
                var go = document.getElementById('wa-send-order');
                if (!go) return;
                // نقرة برمجية على رابط حقيقي: أقل عرضة للمنع من window.open
                go.click();
            })();
        </script>
    @endif
@endsection
