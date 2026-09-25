@extends('layouts.nad')

@section('title', __('account.details') . ' ' . $order->order_code)

@section('content')
    <div class="container-x mt-6 max-w-4xl">
        <a href="{{ route('account.orders') }}" class="mb-4 inline-flex items-center gap-1 text-sm font-bold text-nad-champ hover:underline">
            <x-shop-icon name="chevron" class="h-3 w-3 rotate-180 rtl:-rotate-180" /> {{ __('account.my_orders') }}
        </a>

        <div class="card mb-6 flex flex-wrap items-center justify-between gap-4 p-6">
            <div>
                <p class="text-xs text-nad-dim">{{ __('checkout.your_code') }}</p>
                <p class="mt-1 text-2xl font-extrabold tracking-wider" dir="ltr">{{ $order->order_code }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if ($order->stamped_at || $order->payment_confirmed_at)
                    <a href="{{ route('account.invoice', $order->order_code) }}" target="_blank" rel="noopener"
                       class="btn-gold !py-2 text-sm">🧾 فاتورتي</a>
                @endif
                <span class="badge px-4 py-1.5 text-sm {{ status_badge_class($order->status) }}">
                    {{ \App\Models\Order::statusLabel($order->status) }}
                </span>
            </div>
        </div>

        {{-- محطات سير الطلب — مرتبطة بإجراءات المتجر --}}
        @php
            // حُذفت محطة «تأكيد الطلب»: حالة «مؤكد» أُلغيت لأن القبض صار ختمًا
            // لا مرحلة. والمحطات الآن: استلام ← قبض الدفع ← التحضير ← الشحن/التسليم.
            $flow = ['preparing', 'shipped', 'delivered'];
            $statusesDone = [
                'placed' => true, // استلام الطلب
                'paid' => (bool) $order->payment_confirmed_at, // الختم الأخضر
                'preparing' => in_array($order->status, $flow), // بدء التحضير (يلي القبض)
                'shipped' => in_array($order->status, ['shipped', 'delivered']), // الشحن/التسليم
            ];
            $stations = [
                ['key' => 'placed', 'label' => 'استلام الطلب', 'icon' => 'bag', 'at' => $order->created_at],
                ['key' => 'paid', 'label' => 'قبض الدفع', 'icon' => 'banknote', 'at' => $order->payment_confirmed_at],
                ['key' => 'preparing', 'label' => 'قيد التحضير', 'icon' => 'check', 'at' => $order->statusHistory->firstWhere('to_status', 'preparing')?->created_at],
                ['key' => 'shipped', 'label' => $order->status === 'delivered' ? 'تم التسليم' : 'الشحن / التسليم', 'icon' => 'truck', 'at' => $order->statusHistory->firstWhere('to_status', 'shipped')?->created_at ?? ($order->status === 'delivered' ? $order->updated_at : null)],
            ];
            $cancelled = $order->status === 'cancelled';
        @endphp
        <div class="card mb-6 overflow-x-auto p-6">
            <h2 class="mb-5 font-extrabold">سير الطلب</h2>
            @if ($cancelled)
                <div class="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-[#E9A3B2]">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E9A3B2]/15">
                        <x-shop-icon name="x" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-extrabold">أُلغي هذا الطلب</p>
                        <p class="text-xs text-red-500">{{ $order->statusHistory->last()?->note ?? 'تواصل معنا لأي استفسار' }}</p>
                    </div>
                </div>
            @else
                <div class="flex min-w-[520px] items-start">
                    @foreach ($stations as $i => $st)
                        @php $done = $statusesDone[$st['key']]; @endphp
                        <div class="relative flex flex-1 flex-col items-center text-center" wire:key="station-{{ $st['key'] }}">
                            {{-- الخط الواصل --}}
                            @if ($i > 0)
                                <span class="absolute top-5 {{ config('app.rtl') !== false ? 'right-1/2' : 'left-1/2' }} h-1 w-full
                                      {{ $done ? 'bg-nad-brass' : 'bg-gray-200' }}"
                                      style="inset-inline-start: -50%;"></span>
                            @endif
                            <span class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 transition
                                         {{ $done ? 'border-nad-brass bg-nad-brass text-white shadow-md' : 'border-nad-line2 bg-nad-surface text-nad-dim' }}">
                                <x-shop-icon name="{{ $st['icon'] }}" class="h-5 w-5" />
                            </span>
                            <p class="mt-2 text-xs font-extrabold {{ $done ? 'text-nad-ivory' : 'text-nad-dim' }}">{{ $st['label'] }}</p>
                            @if ($done && $st['at'])
                                <p class="mt-0.5 text-[10px] text-nad-dim">{{ $st['at']->format('m/d H:i') }}</p>
                            @elseif ($done)
                                <p class="mt-0.5 text-[10px] text-nad-brass">✓ تم</p>
                            @else
                                <p class="mt-0.5 text-[10px] text-nad-dim">بالانتظار</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            {{-- المنتجات --}}
            <div class="card p-6 md:col-span-2">
                <h2 class="mb-4 font-extrabold">{{ __('account.items') }}</h2>
                <div class="space-y-3">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 border-b border-gray-50 pb-3 last:border-0 last:pb-0" wire:key="item-{{ $item->id }}">
                            <div>
                                <p class="font-bold">{{ $item->displayName() }}</p>
                                <p class="mt-0.5 text-xs text-nad-dim">
                                    {{ __('product.quantity') }}: {{ $item->quantity }}
                                    @if ($item->is_wholesale) — <span class="font-bold text-nad-champ">{{ __('cart.wholesale_applied') }}</span> @endif
                                </p>
                            </div>
                            <span class="shrink-0 font-extrabold">{{ fmt_usd($item->total_price_usd) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 space-y-2 border-t border-nad-line2 pt-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-nad-mut">{{ __('cart.subtotal') }}</span>
                        <span>{{ fmt_usd($order->subtotal_usd) }}</span>
                    </div>
                    @if ($order->discount_usd > 0)
                        <div class="flex justify-between text-green-600">
                            <span>{{ __('cart.discount') }}</span>
                            <span>-{{ fmt_usd($order->discount_usd) }}</span>
                        </div>
                    @endif
                    @if ($order->shipping_usd > 0)
                        <div class="flex justify-between">
                            <span class="text-nad-mut">{{ __('cart.shipping') }}</span>
                            <span>{{ fmt_usd($order->shipping_usd) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-nad-line2 pt-2 text-base font-extrabold">
                        <span>{{ __('cart.grand_total') }}</span>
                        <span class="text-nad-champ">{{ fmt_usd($order->total_usd) }}</span>
                    </div>
                    <p class="text-end text-xs text-nad-dim">{{ fmt_syp($order->total_syp) }} <span class="opacity-70">(1$ = {{ number_format($order->exchange_rate, 0) }})</span></p>
                </div>
            </div>

            {{-- معلومات إضافية --}}
            <div class="space-y-4">
                <div class="card p-5 text-sm">
                    <h3 class="mb-3 font-extrabold">{{ __('account.details') }}</h3>
                    <div class="space-y-2 text-nad-mut">
                        <p><span class="text-nad-dim">{{ __('account.date') }}:</span> {{ $order->created_at->format('Y/m/d') }}</p>
                        <p><span class="text-nad-dim">{{ __('account.shipping_method') }}:</span> {{ $order->shipping_method === 'local' ? __('checkout.local') : __('checkout.pickup') }}</p>
                        @if ($order->shipping_address)
                            <p><span class="text-nad-dim">{{ __('account.address') }}:</span> {{ $order->shipping_address }}</p>
                        @endif
                        <p><span class="text-nad-dim">{{ __('account.payment') }}:</span> {{ $order->paymentMethod?->name ?? '—' }}</p>
                        @if ($order->notes)
                            <p><span class="text-nad-dim">{{ __('account.notes') }}:</span> {{ $order->notes }}</p>
                        @endif
                    </div>
                </div>

                @if ($order->statusHistory->count() > 1)
                    <div class="card p-5 text-sm">
                        <h3 class="mb-3 font-extrabold">{{ __('account.status') }}</h3>
                        <ol class="space-y-2.5">
                            @foreach ($order->statusHistory as $history)
                                <li class="flex items-center gap-2.5">
                                    <span class="h-2 w-2 rounded-full bg-nad-brass"></span>
                                    <span class="font-bold">{{ \App\Models\Order::statusLabel($history->to_status) }}</span>
                                    <span class="ms-auto text-xs text-nad-dim">{{ $history->created_at?->format('m/d H:i') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
