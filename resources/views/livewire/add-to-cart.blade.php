<div class="space-y-5" wire:key="add-to-cart">
    @if (count($colors) > 0)
        <div>
            <label class="label">{{ __('product.color') }}</label>
            <select wire:model.live="color" class="input max-w-xs">
                <option value="">—</option>
                @foreach ($colors as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            @error('color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    @if (count($sizes) > 0)
        <div>
            <label class="label">{{ __('product.size') }}</label>
            <select wire:model.live="size" class="input max-w-xs">
                <option value="">—</option>
                @foreach ($sizes as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
            @error('size') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="flex items-center gap-3">
        <div>
            <label class="label">{{ __('product.quantity') }}</label>
            <input type="number" wire:model.live="qty" min="1" max="{{ max(1, $maxQty) }}" class="input !w-24 text-center">
        </div>

        <div class="pb-1 pt-6">
            @if ($maxQty > 0 || ! $product->variants->count())
                <span class="badge bg-green-100 text-green-700">
                    <x-shop-icon name="check" class="me-1 h-3 w-3" />
                    {{ $stockMessage }}
                </span>
            @else
                <span class="badge bg-red-100 text-red-700">{{ __('product.out_of_stock') }}</span>
            @endif
        </div>
    </div>
    @error('qty') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    @if ($maxQty > 0 || ! $product->variants->count())
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button wire:click="add" wire:loading.attr="disabled" class="btn-gold flex-1">
                <x-shop-icon name="bag" class="h-4 w-4" />
                {{ __('product.add_to_cart') }}
            </button>
            <button wire:click="add(true)" wire:loading.attr="disabled" class="btn-primary flex-1">
                {{ __('product.buy_now') }}
            </button>

            {{-- إتمام الطلب + إرسال واتساب — بجانب «الشراء الآن».
                 يفتح النافذة الإلزامية لبيانات العميل ثم يرسل تفاصيل المنتج. --}}
            <button type="button"
                    x-on:click="$dispatch('open-customer-details', {
                        target: 'product',
                        productId: {{ $product->id }},
                        variantId: {{ $this->variant?->id ?? 'null' }},
                        quantity: {{ $qty }}
                    })"
                    class="btn-outline flex-1 !border-[#25D366]/60 !text-[#25D366] hover:!bg-[#25D366]/10">
                <x-shop-icon name="whatsapp" class="h-4 w-4" />
                {{ __('checkout.confirm_with_whatsapp') }}
            </button>
        </div>
    @else
        <button disabled class="btn w-full cursor-not-allowed border border-nad-line2 bg-nad-surface2 text-nad-dim">
            {{ __('product.out_of_stock') }}
        </button>
    @endif
</div>
