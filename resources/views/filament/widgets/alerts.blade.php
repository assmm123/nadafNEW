<div class="grid gap-5">

    {{-- ═══ تنبيه البضاعة بنهايك ═══ --}}
    @if (count($lowStock))
        <div class="nad-ocard !p-5" wire:key="low-stock">
            <h3 class="font-display mb-3 text-base text-nad-champ">
                <span class="dia">◆</span> تنبيه البضاعة بنهايك
            </h3>
            <div class="space-y-2">
                @foreach ($lowStock as $item)
                    <div class="flex items-center justify-between gap-3 border border-nad-line2 px-4 py-2.5 {{ $item['out'] ? 'bg-nad-ox/10' : '' }}">
                        <span class="text-sm {{ $item['out'] ? 'font-bold text-red-300' : 'text-nad-ivory' }}">
                            {{ $item['out'] ? '🔴' : '🟡' }} {{ $item['name'] }}
                        </span>
                        <span class="text-xs font-bold {{ $item['out'] ? 'text-red-300' : 'text-nad-champ' }}">
                            {{ $item['out'] ? 'نفدت تمامًا' : 'بقي '.$item['qty'] }}
                        </span>
                    </div>
                @endforeach
            </div>
            <a href="/admin/purchase-invoices" class="nad-btn-brass mt-3 w-full">أنشئ فاتورة شراء</a>
        </div>
    @endif

    {{-- ═══ سلات متروكة ═══ --}}
    @if (count($abandoned))
        <div class="nad-ocard !p-5" wire:key="abandoned">
            <h3 class="font-display mb-3 text-base text-nad-champ">
                <span class="dia">◆</span> سلات متروكة — أقرب بيع ممكن
            </h3>
            <div class="space-y-2">
                @foreach ($abandoned as $c)
                    <div class="flex items-center justify-between gap-3 border border-nad-line2 px-4 py-2.5">
                        <div>
                            <b class="text-sm text-nad-ivory">{{ $c['total'] }}</b>
                            <p class="text-[11px] text-nad-mut">{{ $c['items'] }} — {{ $c['ago'] }}</p>
                        </div>
                        <a href="/admin/chat-logs" class="nad-btn-ghost !px-3 !py-1.5 !text-[11px]">تواصل</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ═══ آخر الطلبيات + واتساب ═══ --}}
    <div class="nad-ocard !p-5" wire:key="recent-orders">
        <h3 class="font-display mb-3 text-base text-nad-champ">
            <span class="dia">◆</span> آخر الطلبيات
        </h3>
        <div class="space-y-2">
            @foreach ($recentOrders as $o)
                <div class="flex flex-wrap items-center justify-between gap-2 border border-nad-line2 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <code class="nad-ocode">{{ $o['code'] }}</code>
                        <span class="text-sm text-nad-ivory">{{ $o['customer'] }}</span>
                        <span class="nad-chip {{ $o['statusColor'] }}">{{ $o['status'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="nad-price !text-base">{{ $o['total'] }}</span>
                        <a href="https://wa.me/{{ $o['phone'] }}?text={{ $o['waText'] }}" target="_blank" rel="noopener"
                           title="مراسلة الزبون واتساب برسالة جاهزة برقم طلبه"
                           class="flex h-9 w-9 items-center justify-center rounded-full bg-[#25D366]/15 text-[#25D366] transition hover:bg-[#25D366]/30">
                            <x-shop-icon name="whatsapp" class="h-4.5 w-4.5" />
                        </a>
                        <a href="{{ $o['viewUrl'] }}" class="nad-btn-ghost !px-3 !py-1.5 !text-[11px]">عرض</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══ شريط السلامة السفلي ═══ --}}
    <div class="nad-ocard flex flex-wrap items-center justify-between gap-3 !px-5 !py-3 text-xs" wire:key="health">
        <span class="flex items-center gap-1.5 text-nad-mut">
            <i class="inline-block h-2 w-2 rounded-full bg-green-400"></i> {{ $health['backup'] }}
        </span>
        <span class="flex items-center gap-1.5 {{ $health['errors'] > 0 ? 'text-red-300 font-bold' : 'text-nad-mut' }}">
            <i class="inline-block h-2 w-2 rounded-full {{ $health['errors'] > 0 ? 'bg-red-400' : 'bg-green-400' }}"></i>
            الأخطاء اليوم: {{ $health['errors'] }}
        </span>
        <span class="flex items-center gap-1.5 text-nad-mut">
            <i class="inline-block h-2 w-2 rounded-full bg-green-400"></i> المجدول: {{ $health['scheduler'] }}
        </span>
    </div>
</div>
