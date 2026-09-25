{{-- النافذة الإلزامية لبيانات العميل — تُفتح من أي زر طلب أو واتساب --}}
<div>
    @if ($open)
        <div class="fixed inset-0 z-[95] flex items-end justify-center bg-black/70 p-4 backdrop-blur-sm sm:items-center"
             wire:click.self="close">
            <div class="w-full max-w-md rounded-2xl border border-nad-line2 bg-nad-surface p-6 shadow-2xl">

                <div class="mb-1 flex items-start justify-between gap-3">
                    <h3 class="font-display text-lg font-bold text-nad-champ">
                        {{ $target === 'product' ? 'إتمام طلب هذا المنتج' : 'إتمام الطلب' }}
                    </h3>
                    <button type="button" wire:click="close"
                            class="rounded-lg p-1 text-nad-mut transition hover:text-nad-champ"
                            aria-label="إغلاق">
                        <x-shop-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <p class="mb-5 text-[12.5px] leading-6 text-nad-mut">
                    أدخل اسمك ورقمك وعنوانك — تُرفق هذه البيانات مع رسالة الطلب.
                    ولا حاجة إلى حساب: التسجيل اختياري.
                </p>

                <form wire:submit="save" class="space-y-3.5">
                    <div>
                        <label class="label" for="cd-name">الاسم الكامل *</label>
                        <input id="cd-name" type="text" wire:model="name" class="input" autofocus>
                        @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="cd-phone">رقم الهاتف *</label>
                        <input id="cd-phone" type="tel" wire:model="phone" class="input" dir="ltr" placeholder="09xxxxxxxx">
                        @error('phone') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="cd-city">المدينة</label>
                        <input id="cd-city" type="text" wire:model="city" class="input">
                        @error('city') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="cd-address">العنوان *</label>
                        <input id="cd-address" type="text" wire:model="address" class="input"
                               placeholder="الحي — الشارع — رقم البناء">
                        @error('address') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled"
                            class="nad-btn-brass w-full !mt-5">
                        <span class="inline-flex items-center justify-center gap-2" wire:loading.remove wire:target="save">
                            <x-shop-icon name="whatsapp" class="h-4 w-4" />
                            إرسال الطلب على واتساب
                        </span>
                        <span wire:loading wire:target="save">⏳ جارٍ الإرسال…</span>
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
