<div wire:key="cart-table">
    @if ($totals['items']->isEmpty())
        {{-- حالة الفراغ بنمط nad --}}
        <div class="nad-empty !grid">
            {{-- كان بلا تحجيم: SVG بلا عرض/ارتفاع يُرسم 300×150 افتراضيًا --}}
            <svg viewBox="0 0 24 24" class="mx-auto h-10 w-10 opacity-60"><path d="M6.5 8h11l-1 12.5a1.5 1.5 0 0 1-1.5 1.4H9a1.5 1.5 0 0 1-1.5-1.4L6.5 8Z"/><path d="M9 10V6.8a3 3 0 0 1 6 0V10"/></svg>
            <h3>{{ __('cart.empty') }}</h3>
            <p>{{ __('cart.empty_desc') }}</p>
            <a href="{{ route('home') }}" class="nad-btn-brass">{{ __('cart.continue_shopping') }}</a>
        </div>
    @else
        <div class="flex flex-col gap-6 lg:flex-row">
            {{-- عناصر السلة — كروت nad-ocard --}}
            <div class="flex-1 space-y-3">
                @foreach ($totals['items'] as $item)
                    <div class="nad-ocard flex items-center gap-4 !p-4" wire:key="item-{{ $item->key }}">
                        @if ($img = $item->product->imageUrl())
                            <img src="{{ $img }}" alt="" class="h-20 w-20 shrink-0 rounded border border-nad-line2 object-cover">
                        @else
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center text-nad-dim" style="border:1px solid rgba(255,255,255,.09)">
                                <x-shop-icon name="bag" class="h-7 w-7" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('product.show', $item->product->slug) }}" class="line-clamp-1 font-bold text-nad-ivory hover:text-nad-champ">
                                {{ $item->product->name }}
                            </a>
                            @if ($item->variant_label)
                                <p class="mt-0.5 text-xs text-nad-mut">{{ $item->variant_label }}</p>
                            @endif
                            <p class="nad-price mt-1 !text-base">
                                {{ fmt_price($item->unit_usd, $item->unit_syp) }}
                            </p>
                            @if ($item->is_wholesale)
                                <span class="nad-chip nad-chip-br mt-1">{{ __('cart.wholesale_applied') }}</span>
                            @endif
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <input type="number" min="1" class="nad-search !w-20 !px-2 !py-1.5 text-center"
                                   wire:model.live.debounce.400ms="qty.{{ $item->key }}">
                            <div class="flex items-center gap-3">
                                <span class="font-extrabold text-nad-champ">{{ fmt_usd($item->line_usd) }}</span>
                                <button wire:click="remove('{{ $item->key }}')" wire:loading.attr="disabled"
                                        class="rounded p-1.5 text-nad-ox hover:bg-nad-ox/20 hover:text-red-300"
                                        title="{{ __('cart.remove') }}">
                                    <x-shop-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ملخص الطلب — صندوق لاصق بنمط nad --}}
            <div class="lg:w-80 lg:shrink-0">
                <div class="nad-ocard sticky top-24 space-y-3 !p-5 text-sm">
                    <h3 class="font-display mb-2 text-lg text-nad-champ">{{ __('cart.summary') }}</h3>
                    <div class="flex justify-between text-nad-mut">
                        <span>{{ __('cart.subtotal') }}</span>
                        <span class="font-bold text-nad-ivory">{{ fmt_usd($totals['subtotal_usd']) }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-nad-dim">
                        <span></span>
                        <span>{{ fmt_syp($totals['total_syp']) }}</span>
                    </div>

                    <div class="border-t border-nad-line2 pt-3">
                        <div class="flex justify-between text-base">
                            <span class="font-extrabold text-nad-ivory">{{ __('cart.grand_total') }}</span>
                            <span class="nad-price !text-xl">{{ fmt_usd($totals['total_usd']) }}</span>
                        </div>
                        <p class="mt-1 text-end text-xs text-nad-mut">{{ fmt_syp($totals['total_syp']) }}</p>
                        <p class="mt-0.5 text-[11px] text-nad-dim">{{ __('cart.shipping') }}: {{ __('cart.calculated_at_checkout') }}</p>
                    </div>

                    {{-- إتمام الطلب متاح للجميع: كان الزائر غير المسجَّل يُحوَّل إلى
                         `/login` فيُشترط عليه حساب — وهو ما مُنع صراحةً. --}}
                    <a href="{{ route('checkout') }}" class="nad-btn-brass w-full">{{ __('cart.checkout') }}</a>

                    {{-- إرسال السلة على واتساب — مقطع منفصل لكل منتج.
                         يُفتح عبر النافذة الإلزامية لبيانات العميل. --}}
                    <button type="button"
                            x-on:click="$dispatch('open-customer-details', { target: 'cart' })"
                            class="nad-btn-ghost w-full !border-[#25D366]/60 !text-[#25D366] hover:!bg-[#25D366]/10">
                        <x-shop-icon name="whatsapp" class="h-4 w-4" />
                        {{ __('checkout.confirm_with_whatsapp') }}
                    </button>

                    <a href="{{ route('home') }}" class="nad-btn-ghost w-full">{{ __('cart.continue_shopping') }}</a>
                </div>
            </div>
        </div>
    @endif
</div>
