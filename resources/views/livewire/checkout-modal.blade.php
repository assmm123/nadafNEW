<div wire:key="checkout-modal-root" x-data="{ show: @js($open) }" x-effect="show = $wire.open">
    <div x-show="show" x-cloak style="display: none; background:rgba(15,19,25,.8); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px)"
         class="nad-modal-overlay fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto p-3"
         @keydown.escape.window="$wire.close()" @click.self="$wire.close()">
        <div class="relative my-6 w-full max-w-lg rounded-[18px] border shadow-2xl nad-modal-box" @click.outside="">

            {{-- الرأس: عنوان font-display فوق خط نحاسي رفيع --}}
            <div class="nad-modal-head flex items-center justify-between px-5 py-3.5">
                <h2 class="flex items-center gap-2 font-display text-base font-bold text-nad-ivory">
                    @if ($step === 'wallets')
                        <x-shop-icon name="card" class="h-5 w-5 text-nad-brass" /> إتمام الدفع — اختر محفظتك
                    @elseif ($step === 'details')
                        <x-shop-icon name="wallet" class="h-5 w-5 text-nad-brass" /> تفاصيل التحويل
                    @else
                        <x-shop-icon name="check" class="h-5 w-5 text-[#7FD3A2]" /> تم بنجاح
                    @endif
                </h2>
                <button wire:click="close" class="rounded-lg p-1.5 text-nad-mut transition hover:bg-white/5 hover:text-nad-champ">
                    <x-shop-icon name="x" class="h-5 w-5" />
                </button>
            </div>

            {{-- مؤشر الخطوات --}}
            @if ($step !== 'success')
                <div class="flex items-center gap-1 px-5 pt-4">
                    <span class="h-1.5 flex-1 rounded-full nad-modal-bar"></span>
                    <span class="h-1.5 flex-1 rounded-full {{ $step === 'details' ? 'nad-modal-bar' : 'bg-white/10' }}"></span>
                </div>
            @endif

            <div class="max-h-[75vh] overflow-y-auto p-5 nad-modal-scroll">

                {{-- ============ 1) شبكة المحافظ ============ --}}
                @if ($step === 'wallets')
                    {{-- ملخص المنتجات + الوصف المنسق --}}
                    @php $first = $cartItems->first(); @endphp
                    @if ($first)
                        <div class="nad-mbox mb-4">
                            @if ($cartItems->count() === 1)
                                <div><span>المنتج</span><b>{{ $first->product->name }}</b></div>
                                @if ($first->product->description)
                                    <p class="line-clamp-3 pt-2 text-[13px] leading-7 text-nad-mut">{{ \Illuminate\Support\Str::limit($first->product->description, 180) }}</p>
                                @endif
                            @else
                                <div><span>الطلب</span><b>{{ $cartItems->count() }} منتجات في طلبك</b></div>
                                <ul class="space-y-0.5 pt-2 text-[13px] text-nad-mut">
                                    @foreach ($cartItems->take(3) as $item)
                                        <li>• {{ $item->product->name }} × {{ $item->qty }}</li>
                                    @endforeach
                                    @if ($cartItems->count() > 3)
                                        <li class="text-xs text-nad-dim">و{{ $cartItems->count() - 3 }} أخرى...</li>
                                    @endif
                                </ul>
                            @endif
                            <div class="flex items-baseline gap-2 pt-2">
                                <span>الإجمالي:</span>
                                <b class="nad-price">{{ fmt_usd($totals['total_usd']) }}</b>
                                <span class="text-xs text-nad-dim">{{ fmt_syp($totals['total_syp']) }}</span>
                            </div>
                        </div>
                    @endif

                    {{-- طريقة الاستلام --}}
                    <div class="mb-4">
                        <p class="mb-2 text-sm font-bold text-nad-ivory">طريقة الاستلام</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="$set('shipping_method', 'pickup')"
                                    class="nad-wcard flex items-center gap-2 px-3 py-2.5 text-sm font-bold transition {{ $shipping_method === 'pickup' ? 'nad-wcard-on' : '' }}">
                                <x-shop-icon name="store" class="h-4.5 w-4.5 text-nad-brass" /> استلام من المحل
                            </button>
                            <button wire:click="$set('shipping_method', 'local')"
                                    class="nad-wcard flex items-center gap-2 px-3 py-2.5 text-sm font-bold transition {{ $shipping_method === 'local' ? 'nad-wcard-on' : '' }}">
                                <x-shop-icon name="truck" class="h-4.5 w-4.5 text-nad-brass" /> توصيل محلي
                            </button>
                        </div>
                    </div>

                    {{-- شبكة الوسائل: مجموعة لكل تصنيف — لا قائمة مسطّحة --}}
                    <p class="mb-3 text-sm font-bold text-nad-ivory">اختر وسيلة الدفع</p>

                    @foreach (collect($paymentMethods)->groupBy('group_label') as $groupLabel => $groupMethods)
                        <div class="mb-4">
                            <div class="mb-2 flex items-center gap-2">
                                <x-shop-icon name="{{ $groupMethods->first()['group_icon'] }}" class="h-4 w-4 text-nad-brass" />
                                <span class="font-display text-[12.5px] font-bold text-nad-champ">{{ $groupLabel }}</span>
                                <span class="text-[11px] text-nad-dim">— {{ $groupMethods->count() }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($groupMethods as $method)
                                    <button wire:click="selectWallet({{ $method['id'] }})"
                                            class="nad-mcard group flex flex-col items-center gap-2 p-3 transition hover:-translate-y-0.5">
                                        <span class="nad-mcard-ic flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl">
                                            @if ($method['icon_url'])
                                                <img src="{{ $method['icon_url'] }}" alt="{{ $method['name'] }}" class="h-full w-full object-contain p-1.5">
                                            @else
                                                <x-shop-icon name="{{ $method['icon'] }}" class="h-8 w-8 text-nad-champ" />
                                            @endif
                                        </span>
                                        <span class="text-center font-display text-[13px] font-bold leading-4 text-nad-ivory">{{ $method['name'] }}</span>
                                        @if ($method['requires_proof'])
                                            <span class="nad-chip nad-chip-br !text-[10px]">يتطلب إثباتًا</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @error('payment_method_id') <p class="mt-2 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror

                    <p class="mt-4 text-center text-[11px] leading-5 text-nad-dim">
                        بالنقر على الوسيلة تظهر تفاصيل التحويل الخاصة بها
                    </p>
                @endif

                {{-- ============ 2) تفاصيل المحفظة ============ --}}
                @if ($step === 'details' && $selectedMethod)
                    <button wire:click="back" class="nad-btn-ghost mb-3 !px-4 !py-2 !text-xs">
                        <x-shop-icon name="chevron" class="h-3.5 w-3.5 rotate-180" /> رجوع للمحافظ
                    </button>

                    {{-- رأس المحفظة: غرفة الوسيلة --}}
                    <div class="mb-4 flex items-center gap-3 rounded-2xl border p-4" style="background:rgba(210,162,78,.07);border-color:rgba(210,162,78,.3)">
                        <span class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl border border-nad-line" style="background:rgba(15,19,25,.55)">
                            @if ($selectedMethod['icon_url'])
                                <img src="{{ $selectedMethod['icon_url'] }}" alt="" class="h-full w-full object-contain p-1">
                            @else
                                <x-shop-icon name="{{ $selectedMethod['icon'] }}" class="h-7 w-7 text-nad-champ" />
                            @endif
                        </span>
                        <div>
                            <p class="font-display text-base font-bold text-nad-ivory">{{ $selectedMethod['name'] }}</p>
                            <p class="text-xs text-nad-champ">{{ \App\Models\PaymentMethod::TYPES[$selectedMethod['type']][app()->getLocale()] ?? '' }}</p>
                        </div>
                    </div>

                    {{-- بيانات التحويل: غرفة الحساب بنمط nad --}}
                    <div class="nad-mbox mb-4">
                        @if ($selectedMethod['account_name'])
                            <div><span>اسم المستلم</span><b>{{ $selectedMethod['account_name'] }}</b></div>
                        @endif
                        @if ($selectedMethod['account_number'])
                            <div><span>الرقم</span><b dir="ltr">{{ $selectedMethod['account_number'] }}</b></div>
                        @endif
                        @if (! empty($selectedMethod['iban']))
                            <div><span>IBAN</span><b dir="ltr" class="tracking-wide">{{ $selectedMethod['iban'] }}</b></div>
                        @endif
                        @if ($selectedMethod['barcode_path'])
                            <img src="{{ $selectedMethod['barcode_url'] }}" alt="barcode" class="mx-auto my-2 h-28 rounded-lg border border-nad-line bg-nad-surface object-contain p-1">
                        @endif
                        @if ($selectedMethod['instructions'])
                            <p class="pt-2 text-[13px] leading-6 text-nad-mut">{{ $selectedMethod['instructions'] }}</p>
                        @endif
                    </div>

                    {{-- الشروط الخاصة — يكتبها المالك لكل وسيلة، وتُعرض في إطار مميّز --}}
                    @if (! empty($selectedMethod['conditions']))
                        <div class="mb-4 rounded-2xl border p-3.5" style="background:rgba(210,162,78,.06);border-color:rgba(210,162,78,.28)">
                            <div class="mb-1.5 flex items-center gap-2">
                                <x-shop-icon name="info" class="h-4 w-4 text-nad-brass" />
                                <span class="font-display text-[12.5px] font-bold text-nad-champ">شروط هذه الوسيلة</span>
                            </div>
                            <p class="text-[12.5px] leading-6 text-nad-mut">{{ $selectedMethod['conditions'] }}</p>
                        </div>
                    @endif

                    {{-- بيانات العميل: إلزامية دائمًا — وهي مصدر الاسم والرقم للطلب
                         بلا حساب. وكانت النافذة لا تجمعها وتُحوّل الزائر إلى
                         `/login`، فيُشترط عليه حساب لا يريده. --}}
                    <div class="mb-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label !text-[13px] !text-nad-mut">الاسم الكامل *</label>
                            <input type="text" wire:model="customer_name" class="nad-search w-full">
                            @error('customer_name') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label !text-[13px] !text-nad-mut">رقم الهاتف *</label>
                            <input type="tel" wire:model="customer_phone" class="nad-search w-full" dir="ltr" placeholder="09xxxxxxxx">
                            @error('customer_phone') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- العنوان إلزامي دائمًا؛ والمدينة للتوصيل المحلي --}}
                    <div class="mb-4 grid gap-3 sm:grid-cols-3">
                        @if ($shipping_method === 'local')
                            <div>
                                <label class="label !text-[13px] !text-nad-mut">المدينة *</label>
                                <input type="text" wire:model="city" class="nad-search w-full" placeholder="دمشق">
                                @error('city') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div class="{{ $shipping_method === 'local' ? 'sm:col-span-2' : 'sm:col-span-3' }}">
                            <label class="label !text-[13px] !text-nad-mut">العنوان *</label>
                            <input type="text" wire:model="address" class="nad-search w-full" placeholder="الحي — الشارع — تفاصيل">
                            @error('address') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- إثبات الدفع --}}
                    @if ($requiresProof)
                        <p class="nad-chip nad-chip-br mb-2 !inline-block !rounded-xl !px-3 !py-2 !text-[11px] !leading-5">
                            بعد تحويل المبلغ: أدخل رقم الحوالة أو ارفع صورة الإيصال — لن يُصدر الكود بدونهما
                        </p>
                    @endif
                    <div class="mb-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label !text-[13px] !text-nad-mut">رقم الحوالة {{ $requiresProof ? '*' : '' }}</label>
                            <input type="text" wire:model="payment_reference" class="nad-search w-full" placeholder="8845213">
                            @error('payment_reference') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label !text-[13px] !text-nad-mut">اسم مرسل الحوالة</label>
                            <input type="text" wire:model="payment_sender_name" class="nad-search w-full" placeholder="الاسم كما في الحوالة">
                        </div>
                    </div>
                    {{-- الحقول المخصصة التي حددها الأدمن لهذه الوسيلة --}}
                    @if (! empty($selectedMethod['dynamic_fields']))
                        <div class="mb-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($selectedMethod['dynamic_fields'] as $df)
                                <div>
                                    <label class="label !text-[13px] !text-nad-mut">{{ $df['label'] }}</label>
                                    @if (($df['type'] ?? 'text') === 'textarea')
                                        <textarea wire:model="dynamicData.{{ $df['label'] }}" rows="2" class="nad-search w-full"></textarea>
                                    @else
                                        <input type="text" wire:model="dynamicData.{{ $df['label'] }}" class="nad-search w-full">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="label !text-[13px] !text-nad-mut">إيصال الدفع (صورة أو PDF)</label>
                        <input type="file" wire:model="proof" accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="block w-full text-sm text-nad-mut file:me-3 file:rounded-lg file:border file:border-nad-line file:bg-nad-surface file:px-4 file:py-2.5 file:font-bold file:text-nad-champ hover:file:border-nad-brass">
                        @error('proof') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                    </div>
                    <div class="mb-4">
                        <label class="label !text-[13px] !text-nad-mut">ملاحظات (اختياري)</label>
                        <textarea wire:model="notes" rows="2" class="nad-search w-full" placeholder="أي تفاصيل إضافية"></textarea>
                    </div>

                    {{-- الإجمالي + تأكيد --}}
                    <div class="mb-3 flex items-center justify-between rounded-xl border border-nad-line px-4 py-3" style="background:rgba(210,162,78,.07)">
                        <span class="text-sm font-bold text-nad-mut">المبلغ المطلوب</span>
                        <span class="nad-price !text-lg">{{ fmt_usd($totals['total_usd']) }}
                            <span class="text-[11px] font-normal text-nad-dim">/ {{ fmt_syp($totals['total_syp']) }}</span>
                        </span>
                    </div>
                    @error('cart') <p class="mb-2 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                    <button wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm"
                            class="nad-btn-brass w-full" wire:loading.class="opacity-60">
                        <span wire:loading.remove wire:target="confirm">✓ تأكيد الدفع</span>
                        <span wire:loading wire:target="confirm">جارٍ الإرسال...</span>
                    </button>
                @endif

                {{-- ============ 3) النجاح ============ --}}
                @if ($step === 'success')
                    <div class="nad-donebox flex flex-col items-center !py-4 text-center">
                        <div class="nad-pulse">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5.5 12.5 4 4 9-9.5"/></svg>
                        </div>
                        <h3 class="mt-4 font-display text-lg font-bold text-nad-ivory">تم استلام طلبك بنجاح</h3>
                        <p class="mt-2 max-w-sm text-sm leading-6 text-nad-mut">
                            شكرًا <b>{{ auth()->user()?->name ?: 'عميلنا العزيز' }}</b> لثقتك بشركة النداف — طلبك قيد المراجعة وسيتم الرد عليك في أسرع وقت ممكن.
                        </p>

                        <p class="mt-4 text-xs tracking-widest text-nad-dim">كود طلبك — احتفظ به</p>
                        <div class="nad-ocodeframe mt-2">{{ $orderCode }}</div>

                        @if ($orderTotals)
                            <p class="nad-price mt-3 !text-base">
                                {{ fmt_usd($orderTotals['total_usd']) }}
                                <span class="text-xs font-normal text-nad-dim">/ {{ fmt_syp($orderTotals['total_syp']) }}</span>
                            </p>
                        @endif

                        <div class="mt-6 flex w-full flex-col gap-2">
                            <a href="{{ route('account.orders') }}" class="nad-btn-brass w-full">تتبع طلبي</a>
                            <a href="{{ route('home') }}" class="nad-btn-ghost w-full">متابعة التسوق</a>
                            <button wire:click="close" class="text-xs font-bold text-nad-dim transition hover:text-nad-champ">إغلاق</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
