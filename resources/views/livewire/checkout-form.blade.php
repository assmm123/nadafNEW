<div wire:key="checkout-form" x-data
     x-on:nad-scroll-top.window="window.scrollTo({top:0,behavior:'smooth'})">

    @error('general')
        <div class="nad-ocard !p-4 !mb-5 text-sm font-bold text-red-300">{{ $message }}</div>
    @enderror
    @error('cart')
        <div class="nad-ocard !p-4 !mb-5 text-sm font-bold text-red-300">
            {{ $message }}
            <a href="{{ route('cart.index') }}" class="ms-2 underline">{{ __('cart.title') }}</a>
        </div>
    @enderror

    {{-- ═══ paytop + الخطوات — بنية nad.html ═══ --}}
    <div class="nad-paytop">
        @if ($step === 1)
            <a href="{{ route('cart.index') }}" class="nad-btn-ghost">
                <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M14.5 6l-6 6 6 6"/></svg>
                {{ __('checkout.back_to_cart') }}
            </a>
        @elseif ($step === 2)
            <button type="button" wire:click="backToFields" class="nad-btn-ghost">
                <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M14.5 6l-6 6 6 6"/></svg>
                {{ __('checkout.back_to_order') }}
            </button>
        @else
            <button type="button" wire:click="backToPayment" class="nad-btn-ghost">
                <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M14.5 6l-6 6 6 6"/></svg>
                {{ __('checkout.back_to_payment') }}
            </button>
        @endif
        <h2 class="!mb-0"><span class="dia">◆</span>{{ __('checkout.complete_order') }}</h2>
    </div>

    <div class="nad-steps">
        <div class="nad-stp {{ $step === 1 ? 'on cur' : ($step > 1 ? 'on' : '') }}">
            <i>{{ $step > 1 ? '✓' : '1' }}</i><b>{{ __('checkout.step_order') }}</b>
        </div><span class="nad-sln {{ $step > 1 ? 'on' : '' }}"></span>
        <div class="nad-stp {{ $step === 2 ? 'on cur' : ($step > 2 ? 'on' : '') }}">
            <i>{{ $step > 2 ? '✓' : '2' }}</i><b>{{ __('checkout.step_payment') }}</b>
        </div><span class="nad-sln {{ $step > 2 ? 'on' : '' }}"></span>
        <div class="nad-stp {{ $step === 3 ? 'on cur' : '' }}">
            <i>3</i><b>{{ __('checkout.step_confirm') }}</b>
        </div>
    </div>

    {{-- ═══ الخطوة 1: بيانات الطلب ═══ --}}
    @if ($step === 1)
        <div class="nad-fgrid">
            {{-- بيانات العميل: **حقول حقيقية** لا نصّ مقروء.
                 كانت `value="{{ auth()->user()->name }}"` بلا فحص — أي أنها
                 تسقط بخطأ 500 لأي زائر غير مسجَّل، وهو ما كان يمنع الطلب بلا
                 حساب أصلًا. وهي الآن إلزامية ويملؤها الضيف، والمسجَّل تُعبَّأ
                 له مسبقًا فيبقى قادرًا على تعديلها. --}}
            <div class="nad-fld">
                <label>{{ __('checkout.full_name') }} *</label>
                <input type="text" wire:model="customer_name" placeholder="{{ __('checkout.full_name') }}">
                @error('customer_name') <p class="text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
            <div class="nad-fld">
                <label>{{ __('auth.phone') }} *</label>
                <input type="tel" wire:model="customer_phone" dir="ltr" placeholder="09xxxxxxxx">
                @error('customer_phone') <p class="text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
            <div class="nad-fld full">
                <label>{{ __('checkout.shipping_method') }} *</label>
                <div class="flex gap-2.5 flex-wrap">
                    <button type="button" wire:click="$set('shipping_method', 'pickup')"
                            class="nad-btn-ghost !rounded-full {{ $shipping_method === 'pickup' ? '!border-nad-brass !text-nad-champ !bg-nad-brass/10' : '' }}">
                        🏬 {{ __('checkout.pickup') }}
                    </button>
                    <button type="button" wire:click="$set('shipping_method', 'local')"
                            class="nad-btn-ghost !rounded-full {{ $shipping_method === 'local' ? '!border-nad-brass !text-nad-champ !bg-nad-brass/10' : '' }}">
                        🌍 {{ __('checkout.shipping_worldwide') }}
                    </button>
                </div>
                @if ($shipping_method === 'local')
                    <p class="text-[11px] text-nad-mut mt-2">{{ __('checkout.shipping_note') }}</p>
                @endif
            </div>
            {{-- المدينة: للتوصيل المحلي. والعنوان: **إلزامي دائمًا** — ولو كان
                 الاستلام من المتجر يبقى وسيلة تواصل ومرجعًا للطلب. --}}
            @if ($shipping_method === 'local')
                <div class="nad-fld">
                    <label>{{ __('checkout.city') }} *</label>
                    <input type="text" wire:model="city" placeholder="{{ __('checkout.city_placeholder') }}">
                    @error('city') <p class="text-xs text-red-300">{{ $message }}</p> @enderror
                </div>
            @endif
            <div class="nad-fld {{ $shipping_method === 'local' ? '' : 'full' }}">
                <label>{{ __('checkout.address_detail') }} *</label>
                <input type="text" wire:model="address" placeholder="{{ __('checkout.address_placeholder') }}">
                @error('address') <p class="text-xs text-red-300">{{ $message }}</p> @enderror
            </div>
            <div class="nad-fld full">
                <label>{{ __('checkout.order_notes') }}</label>
                <textarea wire:model="notes" placeholder="{{ __('checkout.notes_placeholder') }}"></textarea>
            </div>
            <div class="nad-fld full">
                <button type="button" wire:click="gotoPayment" wire:loading.attr="disabled" class="nad-btn-brass">
                    {{ __('checkout.continue_to_payment') }}
                </button>
            </div>
        </div>
    @endif

    {{-- ═══ الخطوة 2: وسيلة الدفع ═══ --}}
    @if ($step === 2)
        @if (! $selectedMethod)
            {{-- الوسائل مصنَّفة بمجموعات — لا شبكة مسطّحة --}}
            @foreach (collect($paymentMethods)->groupBy('group_label') as $groupLabel => $groupMethods)
                <div class="mb-5">
                    <div class="mb-2 flex items-center gap-2">
                        <x-shop-icon name="{{ $groupMethods->first()['group_icon'] }}" class="h-4 w-4 text-nad-brass" />
                        <span class="font-display text-[13px] font-bold text-nad-champ">{{ $groupLabel }}</span>
                        <span class="text-[11px] text-nad-dim">— {{ $groupMethods->count() }}</span>
                    </div>

                    <div class="nad-paygrid">
                        @foreach ($groupMethods as $m)
                            <button type="button" wire:click="selectMethod({{ $m['id'] }})" class="nad-mcard" wire:key="pm-{{ $m['id'] }}">
                                @if ($m['icon_url'])
                                    <img class="ph" src="{{ $m['icon_url'] }}" alt="" style="object-fit:contain;background:#fff;padding:14px">
                                @else
                                    {{-- بلا صورة خارجية (كانت picsum.photos) — خلفية نحاسية من الهوية --}}
                                    <span class="ph" aria-hidden="true" style="background:radial-gradient(420px 240px at 80% 15%, rgba(210,162,78,.3), transparent 65%), linear-gradient(160deg,#1B222D,#0F1319)"></span>
                                @endif
                                <span class="nad-shade"></span>
                                <span class="nad-gicon"><x-shop-icon name="{{ $m['icon'] }}" class="h-5 w-5" /></span>
                                <div class="nad-gmeta">
                                    <div>
                                        <h3 class="font-display">{{ $m['name'] }}</h3>
                                        <p>{{ \App\Models\PaymentMethod::TYPES[$m['type']]['ar'] ?? $m['type'] }}</p>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @error('payment_method_id') <p class="text-xs text-red-300 !mb-4">{{ $message }}</p> @enderror
        @else
            {{-- غرفة الوسيلة المختارة — mbox بنمط nad.html --}}
            <div class="nad-mbox">
                <button type="button" wire:click="backToMethods" class="nad-btn-ghost !py-2 !px-4 !text-xs">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5"><path d="M14.5 6l-6 6 6 6"/></svg>
                    {{ __('checkout.back_to_wallets') }}
                </button>
                <h3 class="font-display !mt-4">{{ $selectedMethod['name'] }}</h3>
                @if ($selectedMethod['instructions'])
                    <p class="nad-jnote !text-nad-mut">{{ $selectedMethod['instructions'] }}</p>
                @endif

                {{-- الشروط الخاصة — يكتبها المالك لكل وسيلة --}}
                @if (! empty($selectedMethod['conditions']))
                    <div class="!mt-3 rounded-xl border p-3" style="background:rgba(210,162,78,.06);border-color:rgba(210,162,78,.28)">
                        <div class="mb-1 flex items-center gap-2">
                            <x-shop-icon name="info" class="h-4 w-4 text-nad-brass" />
                            <span class="font-display text-[12.5px] font-bold text-nad-champ">{{ __('checkout.method_conditions') }}</span>
                        </div>
                        <p class="text-[12.5px] leading-6 text-nad-mut">{{ $selectedMethod['conditions'] }}</p>
                    </div>
                @endif

                {{-- صفوف الحساب الحقيقية --}}
                @if ($selectedMethod['account_name'] || $selectedMethod['account_number'] || $selectedMethod['iban'])
                    <div class="nad-acct">
                        @if ($selectedMethod['account_name'])
                            <div><span>{{ __('checkout.beneficiary_name') }}</span><b>{{ $selectedMethod['account_name'] }}</b></div>
                        @endif
                        @if ($selectedMethod['account_number'])
                            <div><span>{{ __('checkout.beneficiary_number') }}</span><b dir="ltr">{{ $selectedMethod['account_number'] }}</b></div>
                        @endif
                        @if ($selectedMethod['iban'])
                            <div><span>IBAN</span><b dir="ltr">{{ $selectedMethod['iban'] }}</b></div>
                        @endif
                    </div>
                @endif

                {{-- الباركود الحقيقي أو المرسوم --}}
                <div class="nad-barwrap">
                    @if ($selectedMethod['barcode_url'])
                        <img src="{{ $selectedMethod['barcode_url'] }}" alt="barcode">
                    @else
                        <div class="nad-barcode"></div>
                    @endif
                    <code>{{ $selectedMethod['name'] }} — NDF-PAY</code>
                    <small>{{ __('checkout.barcode_note') }}</small>
                </div>

                {{-- حقول الحوالة + رفع الإيصال الحي --}}
                <div class="nad-fgrid !pb-0">
                    <div class="nad-fld">
                        <label>{{ $requiresProof ? __('checkout.transfer_ref_required') : __('checkout.transfer_ref_optional') }}</label>
                        <input type="text" wire:model="payment_reference" placeholder="{{ __('checkout.transfer_ref_placeholder') }}">
                        @error('payment_reference') <p class="text-xs text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div class="nad-fld">
                        <label>{{ __('order.sender_name') }}</label>
                        <input type="text" wire:model="payment_sender_name" placeholder="{{ __('checkout.sender_placeholder') }}">
                        @error('payment_sender_name') <p class="text-xs text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div class="nad-fld full">
                        <label class="nad-upl">
                            <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M12 16V5.5M7.5 9.5 12 5l4.5 4.5"/><path d="M5 16.5v2A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5v-2"/></svg>
                            @if ($proof)
                                ✓ {{ $proof->getClientOriginalName() }} — {{ __('checkout.click_to_change') }}
                            @else
                                {{ __('checkout.attach_receipt') }}
                            @endif
                            <input type="file" wire:model="proof" accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </label>
                        @error('proof') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                        @if ($requiresProof)
                            <p class="text-[11px] text-nad-dim mt-1">{{ __('checkout.proof_required_note') }}</p>
                        @endif
                    </div>
                </div>

                @if (! $requiresProof)
                    <p class="nad-jnote">{{ __('checkout.no_proof_note') }}</p>
                @endif

                <button type="button" wire:click="gotoSummary" wire:loading.attr="disabled" class="nad-btn-brass !mt-2">
                    {{ __('checkout.continue_to_confirm') }}
                </button>
            </div>
        @endif
    @endif

    {{-- ═══ الخطوة 3: ملخص الطلب النهائي ═══ --}}
    @if ($step === 3)
        <div class="nad-sumwrap">
            <div class="nad-mbox !mb-0">
                <h3 class="font-display">{{ __('checkout.final_summary') }}</h3>
                <div class="nad-acct !mt-4">
                    {{-- من حالة النموذج لا من `auth()->user()` — الحساب قد لا
                         يوجد أصلًا (طلب ضيف)، والاستدعاء المباشر يسقط 500. --}}
                    <div><span>{{ __('checkout.name_label') }}</span><b>{{ $customer_name ?: '—' }}</b></div>
                    <div><span>{{ __('checkout.phone_label') }}</span><b dir="ltr">{{ $customer_phone ?: '—' }}</b></div>
                    <div><span>{{ __('checkout.payment_method_label') }}</span><b>{{ $selectedMethod['name'] }}</b></div>
                    <div><span>{{ __('order.address') }}</span><b>{{ trim(($city ? $city.' — ' : '').$address) ?: '—' }}</b></div>
                </div>

                <div class="nad-sumitems">
                    @foreach ($totals['items'] as $item)
                        <div>
                            <span>{{ $item->product->name }} @if($item->variant_label) · {{ $item->variant_label }} @endif · ×{{ $item->qty }}</span>
                            <b>{{ fmt_usd($item->line_usd) }}</b>
                        </div>
                    @endforeach
                </div>

                <div class="nad-acct !border-nad-line2 !bg-transparent">
                    <div><span>{{ __('cart.subtotal') }}</span><b>{{ fmt_usd($totals['subtotal_usd']) }}</b></div>
                    @if ($totals['discount_usd'] > 0)
                        <div><span>{{ __('cart.coupon_discount') }}</span><b style="color:#7FD3A2">-{{ fmt_usd($totals['discount_usd']) }}</b></div>
                    @endif
                    @if ($shipping_method === 'local')
                        <div><span>{{ __('cart.shipping') }}</span><b>{{ $totals['shipping_usd'] > 0 ? fmt_usd($totals['shipping_usd']) : __('checkout.free') }}</b></div>
                    @endif
                </div>

                <div class="nad-acct !bg-nad-brass/10" style="border-color:var(--brass,#D2A24E)">
                    <div class="!items-baseline">
                        <span class="!text-nad-ivory font-extrabold">{{ __('checkout.total_label') }}
                            <small class="block text-[10.5px] text-nad-dim font-normal">{{ fmt_syp($totals['total_syp']) }}</small>
                        </span>
                        <b class="font-display !text-2xl text-nad-champ">{{ fmt_usd($totals['total_usd']) }}</b>
                    </div>
                </div>

                <button type="button" wire:click="confirm" wire:loading.attr="disabled" class="nad-btn-brass w-full !mt-4">
                    <span wire:loading.remove wire:target="confirm">{{ __('checkout.confirm_payment') }}</span>
                    <span wire:loading wire:target="confirm">⏳ {{ __('checkout.processing') }}</span>
                </button>

                {{-- إتمام الطلب + إرسال واتساب: يُنشئ الطلب أولًا (فلا يضيع إن
                     أُغلق واتساب) ثم يفتحه بالتفاصيل كاملة. --}}
                <button type="button" wire:click="confirmWithWhatsapp" wire:loading.attr="disabled"
                        class="nad-btn-ghost w-full !mt-2.5 !border-[#25D366]/60 !text-[#25D366] hover:!bg-[#25D366]/10">
                    <span class="inline-flex items-center justify-center gap-2" wire:loading.remove wire:target="confirmWithWhatsapp">
                        <x-shop-icon name="whatsapp" class="h-4 w-4" />
                        {{ __('checkout.confirm_with_whatsapp') }}
                    </span>
                    <span wire:loading wire:target="confirmWithWhatsapp">⏳ {{ __('checkout.processing') }}</span>
                </button>
                <p class="mt-3 text-center text-[11px] text-nad-dim">
                    {{ __('checkout.confirm_note') }}
                </p>
            </div>
            <p class="nad-jnote !text-sm !leading-8" style="color:#A79F8F">
                {{ __('checkout.after_note') }}
            </p>
        </div>
    @endif
</div>
