@extends('layouts.nad')

@section('title', __('account.title'))

@section('content')
    <div class="container-x py-8">
        <div class="nad-sec nad-sec-sm !mb-6">
            <h2 class="!text-2xl"><span class="dia">◆</span>حسابي</h2>
            <p class="!mb-0 text-sm text-nad-mut">بيانات الدخول والملف الشخصي — التتبع عبر بوابة «طلباتي»</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- بيانات الدخول والملف --}}
            <div class="nad-ocard !p-6">
                <h3 class="font-display mb-5 text-lg text-nad-champ">{{ __('account.profile') }}</h3>

                @if (session('success'))
                    <div class="mb-4 border border-nad-line bg-nad-surface2 p-3 text-sm font-bold text-nad-champ">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-nad-mut" for="name">{{ __('auth.name') }}</label>
                        <div class="nad-search"><input id="name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required></div>
                        @error('name') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-nad-mut" for="email">{{ __('auth.email') }}</label>
                        <div class="nad-search !opacity-60"><input type="email" value="{{ auth()->user()->email }}" disabled dir="ltr"></div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-nad-mut" for="phone">{{ __('auth.phone') }}</label>
                        <div class="nad-search"><input id="phone" type="tel" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required dir="ltr"></div>
                        @error('phone') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-nad-mut" for="password">{{ __('auth.password') }}</label>
                            <div class="nad-search"><input id="password" type="password" name="password" placeholder="••••••" dir="ltr"></div>
                            @error('password') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-nad-mut" for="password_confirmation">{{ __('auth.confirm_password') }}</label>
                            <div class="nad-search"><input id="password_confirmation" type="password" name="password_confirmation" placeholder="••••••" dir="ltr"></div>
                        </div>
                    </div>

                    <button class="nad-btn-brass w-full">{{ __('account.save') }}</button>
                </form>
            </div>

            {{-- آخر الطلبات — اختصار يؤدي إلى بوابة طلباتي --}}
            <div class="nad-ocard !p-6">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="font-display text-lg text-nad-champ">{{ __('account.my_orders') }}</h3>
                    <a href="{{ route('account.orders') }}" class="nad-btn-ghost !px-4 !py-2 !text-xs">بوابة طلباتي ←</a>
                </div>

                @if ($recentOrders->isEmpty())
                    <div class="py-8 text-center text-sm text-nad-mut">{{ __('account.no_orders') }}</div>
                @else
                    <div class="space-y-3">
                        @foreach ($recentOrders as $order)
                            <a href="{{ route('account.orders') }}"
                               class="flex items-center justify-between gap-3 border border-nad-line2 p-4 transition hover:border-nad-brass">
                                <div>
                                    <code class="nad-ocode">{{ $order->order_code }}</code>
                                    <p class="mt-1 text-xs text-nad-mut">{{ $order->created_at->format('Y/m/d') }} — {{ $order->items_count ?? $order->items->count() }} {{ __('account.items') }}</p>
                                </div>
                                <div class="text-end">
                                    <span class="nad-chip {{ $order->status === 'cancelled' ? 'nad-chip-ox' : ($order->status === 'delivered' ? 'nad-chip-ok' : 'nad-chip-wr') }}">{{ \App\Models\Order::statusLabel($order->status) }}</span>
                                    <p class="nad-price mt-1 text-base">{{ fmt_usd($order->total_usd) }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <p class="mt-4 text-center text-xs text-nad-mut">التتبع الكامل بمحطاته متاح داخل بوابة «طلباتي»</p>
                @endif
            </div>
        </div>
    </div>
@endsection
