<div class="container-x nad-portal py-6" wire:key="my-orders-root">
    <div class="nad-sec nad-sec-sm mb-2 flex flex-wrap items-center justify-between gap-3">
        <h2 class="!text-2xl"><span class="dia">◆</span>{{ __('portal.title') }}</h2>
        <button type="button" onclick="history.back()" class="nad-btn-ghost !px-4 !py-2 !text-xs">{{ __('common.back') }}</button>
    </div>

    {{-- ═══ قسم التتبع: الطلبيات الحية + المكتملة خلال 24 ساعة ═══ --}}
    <div class="mb-8 space-y-5" wire:key="tracking">
        <div class="nad-sec nad-sec-sm !mb-0 flex items-center justify-between gap-3 flex-wrap">
            <h3 class="!text-base"><span class="dia">◆</span>{{ __('portal.tracking') }}</h3>
            <span class="text-xs text-nad-mut">{{ __('portal.tracking_note') }}</span>
        </div>

        @forelse ($tracking as $t)
            <div class="nad-ocard !p-4" wire:key="tr-{{ $t['code'] }}">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <code class="nad-ocode">{{ $t['code'] }}</code>
                        <span class="nad-odate">{{ $t['date'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- عداد ساعات الاختفاء من التتبع (للمكتملة فقط) --}}
                        @if ($t['hideIn'] !== null)
                            <span class="nad-chip nad-chip-in" title="{{ __('portal.hide_in_note') }}">
                                ⏳ {{ __('portal.hours_left', ['count' => $t['hideIn']]) }}
                            </span>
                        @endif
                        @if ($t['paid'])
                            <span class="nad-chip nad-chip-ok"><i></i>{{ __('portal.paid') }}</span>
                        @endif
                        @if ($t['stamped'])
                            <span class="nad-chip nad-chip-br"><i></i>{{ __('portal.stamped') }}</span>
                        @endif
                        @if ($t['dead'])
                            <span class="nad-chip nad-chip-ox">{{ __('portal.dead') }}</span>
                        @endif
                    </div>
                </div>
                {{-- المحطات الأفقية — jnode/jln بنمط nad.html --}}
                <div class="flex items-center overflow-x-auto py-1">
                    @foreach ($stages as $i => $sName)
                        @php $on = ! $t['dead'] && ($i + 1) <= $t['stage']; $cur = ! $t['dead'] && ($i + 1) === $t['stage']; @endphp
                        <div class="nad-jnode {{ $on ? 'on' : '' }} {{ $cur ? 'cur' : '' }}">
                            <i>{{ $t['dead'] && $i === count($stages) - 1 ? '✕' : $i + 1 }}</i>
                            <span>{{ $sName }}</span>
                        </div>
                        @if (! ($i === count($stages) - 1))
                            <span class="nad-jln {{ (! $t['dead'] && $i + 1 < $t['stage']) ? 'on' : '' }}"></span>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="nad-ocard !p-8 text-center text-sm text-nad-mut">
                {{ __('portal.tracking_empty') }}
            </div>
        @endforelse
    </div>

    {{-- ═══ الكرتان: المستلمة + الملغية ═══ --}}

    {{-- كرت الطلبات المستلمة (مع فواتيرها) --}}
    <button type="button" wire:click="openSection('delivered')" class="nad-gcard mb-5" wire:key="gate-delivered">
        {{-- بلا صورة خارجية (كانت picsum.photos) — تدرّج من الهوية --}}
        <span class="ph" aria-hidden="true" style="background:radial-gradient(420px 240px at 82% 15%, rgba(210,162,78,.34), transparent 65%), linear-gradient(160deg,#1B222D,#0F1319);filter:none"></span>
        <span class="nad-shade"></span>
        <span class="nad-gicon">
            <x-shop-icon name="store" class="h-5 w-5" />
        </span>
        <div class="nad-gmeta">
            <div>
                <h3 class="font-display">{{ __('portal.delivered') }}</h3>
                <p>{{ __('portal.delivered_note') }}</p>
            </div>
            <span class="nad-gpill"><b>{{ count($delivered) }}</b><small>{{ __('portal.order_unit') }}</small></span>
        </div>
    </button>

    {{-- كرت الملغية --}}
    <button type="button" wire:click="openSection('cancelled')" class="nad-gcard mb-8" wire:key="gate-cancelled">
        {{-- بلا صورة خارجية (كانت picsum.photos) — تدرّج من الهوية --}}
        <span class="ph" aria-hidden="true" style="background:radial-gradient(420px 240px at 82% 15%, rgba(142,59,78,.4), transparent 65%), linear-gradient(160deg,#1B222D,#0F1319);filter:none"></span>
        <span class="nad-shade"></span>
        <span class="nad-gbadge">{{ __('portal.refund_follow') }}</span>
        <span class="nad-gicon">
            <x-shop-icon name="x" class="h-5 w-5" />
        </span>
        <div class="nad-gmeta">
            <div>
                <h3 class="font-display">{{ __('portal.cancelled') }}</h3>
                <p>{{ __('portal.cancelled_note') }}</p>
            </div>
            <span class="nad-gpill"><b>{{ count($cancelled) }}</b><small>{{ __('portal.order_unit') }}</small></span>
        </div>
    </button>

    {{-- ═══ الغرفة المعزولة: تفاصيل كاملة ═══ --}}
    @if ($openSection)
        <div class="nad-room-head">
            <div class="container-x nad-room-row">
                <button type="button" wire:click="closeSection" aria-label="{{ __('common.back') }}" class="nad-room-back">
                    <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M14.5 6l-6 6 6 6"/></svg>
                    {{ __('common.back') }}
                </button>
                <div class="nad-room-title">
                    <h2>{{ $openSection === 'delivered' ? __('portal.delivered') : __('portal.cancelled') }}</h2>
                    <small>{{ $openSection === 'delivered' ? __('portal.delivered_room_note') : __('portal.cancelled_room_note') }}</small>
                </div>
                <span class="nad-room-count"><i class="dot"></i><b>{{ $openSection === 'delivered' ? count($delivered) : count($cancelled) }}</b> {{ __('portal.order_unit') }}</span>
            </div>
        </div>

        @if ($openSection === 'delivered')
            <div class="nad-olist">
                @forelse ($delivered as $o)
                    <article class="nad-ocard" wire:key="dl-{{ $o['code'] }}">
                        <a href="{{ route('account.order', $o['code']) }}" class="nad-ohead">
                            <div class="nad-omain">
                                <div class="nad-otop">
                                    <code class="nad-ocode">{{ $o['code'] }}</code>
                                    <span class="nad-odate">{{ $o['date'] }}</span>
                                    @if ($o['stamped'])
                                        <span class="nad-chip nad-chip-br"><i></i>{{ __('portal.stamped') }}</span>
                                    @endif
                                </div>
                                <p class="nad-oitems">{{ $o['items'] }} {{ __('portal.item_unit') }} · {{ $o['payment'] }}</p>
                            </div>
                            <div class="nad-ototal">
                                <b class="nad-price">{{ $o['total'] }}</b>
                                <span>{{ $o['totalSyp'] }}</span>
                            </div>
                        </a>
                        <div class="nad-obody">
                            @if ($o['invoice'])
                                <a href="{{ route('account.invoice', $o['code']) }}" target="_blank" class="nad-btn-ghost nad-btn-sm">
                                    <x-shop-icon name="wallet" class="h-4 w-4" /> {{ __('portal.invoice') }}
                                </a>
                            @endif
                            <a href="{{ route('account.order', $o['code']) }}" class="nad-btn-ghost nad-btn-sm">
                                {{ __('portal.full_details') }}
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="nad-empty">
                        {{-- كان بلا تحجيم: SVG بلا عرض/ارتفاع يُرسم 300×150 افتراضيًا --}}
                        <svg viewBox="0 0 24 24" class="mx-auto h-10 w-10 opacity-60"><path d="M6.5 8h11l-1 12.5a1.5 1.5 0 0 1-1.5 1.4H9a1.5 1.5 0 0 1-1.5-1.4L6.5 8Z"/><path d="M9 10V6.8a3 3 0 0 1 6 0V10"/></svg>
                        <h3>{{ __('portal.delivered_empty') }}</h3>
                        <p>{{ __('portal.delivered_empty_note') }}</p>
                        <a href="{{ route('home') }}" class="nad-btn-brass">{{ __('portal.start_shopping') }}</a>
                    </div>
                @endforelse
            </div>
        @else
            <div class="nad-olist">
                @forelse ($cancelled as $o)
                    <article class="nad-ocard" wire:key="cx-{{ $o['code'] }}">
                        <div class="nad-ohead !cursor-default">
                            <div class="nad-omain">
                                <div class="nad-otop">
                                    <code class="nad-ocode">{{ $o['code'] }}</code>
                                    <span class="nad-odate">{{ $o['date'] }}</span>
                                </div>
                                <p class="nad-oitems">{{ $o['items'] }} عنصر · {{ $o['payment'] }}</p>
                            </div>
                            <div class="nad-ototal">
                                <b class="nad-price">{{ $o['total'] }}</b>
                                <span>{{ $o['totalSyp'] }}</span>
                            </div>
                        </div>
                        <div class="nad-obody">
                            <div class="nad-chip nad-chip-ox w-fit"><i></i>سبب الإلغاء: {{ $o['reason'] }}</div>
                            <a href="{{ route('contact') }}" class="nad-btn-ghost nad-btn-sm w-fit">تواصل معنا بخصوصها</a>
                        </div>
                    </article>
                @empty
                    <div class="nad-empty">
                        <svg viewBox="0 0 24 24" class="mx-auto"><circle cx="12" cy="12" r="9.2"/><path d="m9 9 6 6M15 9l-6 6"/></svg>
                        <h3>لا طلبيات ملغية</h3>
                        <p>كل طلبياتك سليمة — هذا الكرت فارغ وهذا رائع</p>
                    </div>
                @endforelse
            </div>
        @endif

        <div class="mt-8 pb-10 text-center">
            <button type="button" wire:click="closeSection" class="nad-btn-ghost">رجوع للبوابة</button>
        </div>
    @endif
</div>
