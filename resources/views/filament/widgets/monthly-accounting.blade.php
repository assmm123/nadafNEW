{{-- الطبقة الثانية: المحاسبة — هذا الشهر --}}
@php $a = $this->getAccounting(); @endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-3">
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-[#D2A24E]">المحاسبة — {{ $a['label'] }}</p>
        <a href="{{ route('admin.reports.export', ['period' => 'this_month', 'type' => 'xlsx']) }}"
           class="ms-auto rounded-full border border-white/15 px-3.5 py-1.5 text-[12px] text-[#A79F8F] transition hover:border-[#D2A24E] hover:text-[#EAC97F]">
            تصدير Excel
        </a>
        <a href="{{ route('admin.reports.export', ['period' => 'this_month', 'type' => 'pdf']) }}" target="_blank"
           class="rounded-full border border-white/15 px-3.5 py-1.5 text-[12px] text-[#A79F8F] transition hover:border-[#D2A24E] hover:text-[#EAC97F]">
            تقرير PDF
        </a>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-[#171D26] p-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-[11.5px] text-[#A79F8F]">المبيعات</span>
                    <b class="text-[18px] font-medium text-[#F1ECE1]">{{ number_format($a['sales'], 2) }} <span class="text-[11px] text-[#8A93A3]">$</span></b>
                </div>
                <div>
                    <span class="block text-[11.5px] text-[#A79F8F]">تكلفة البضاعة</span>
                    <b class="text-[18px] font-medium text-[#F1ECE1]">{{ number_format($a['cogs'], 2) }} <span class="text-[11px] text-[#8A93A3]">$</span></b>
                </div>
                <div>
                    <span class="block text-[11.5px] text-[#A79F8F]">الربح الإجمالي</span>
                    <b class="text-[18px] font-medium text-[#7FD3A2]">{{ number_format($a['profit'], 2) }} <span class="text-[11px] text-[#8A93A3]">$</span></b>
                </div>
                <div>
                    <span class="block text-[11.5px] text-[#A79F8F]">هامش الربح</span>
                    <b class="text-[18px] font-medium text-[#EAC97F]">{{ $a['margin'] }}%</b>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1.5 border-t border-white/10 pt-3 text-[11.5px]">
                <span class="text-[#A79F8F]">{{ $a['orders'] }} طلبًا · {{ $a['items'] }} قطعة</span>
                @if ($a['best'])
                    <span class="text-[#A79F8F]">أعلى ربحية: <b class="text-[#F1ECE1]">{{ $a['best']['name'] }}</b> {{ $a['best']['margin'] }}%</span>
                @endif
                @if ($a['worst'])
                    <span class="text-[#A79F8F]">أضعف هامش: <b class="text-[#E9A3B2]">{{ $a['worst']['name'] }}</b> {{ $a['worst']['margin'] }}%</span>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-[#171D26] p-4">
            <span class="text-[11px] font-bold uppercase tracking-[.14em] text-[#D2A24E]">المبيعات اليومية</span>

            @if ($a['bars'] === [])
                <p class="mt-6 text-[12.5px] text-[#8A93A3]">لا مبيعات هذا الشهر بعد.</p>
            @else
                <div class="mt-4 flex h-[92px] items-end gap-[3px]">
                    @foreach ($a['bars'] as $bar)
                        <span class="flex-1 rounded-[3px]"
                              style="height:{{ $bar['height'] }}%;background:{{ $loop->last ? '#D2A24E' : 'rgba(210,162,78,.32)' }}"
                              title="{{ $bar['day'] }} — {{ number_format($bar['sales'], 2) }} $"></span>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-[10.5px] text-[#8A93A3]" dir="ltr">
                    <span>{{ $a['bars'][0]['day'] ?? '' }}</span>
                    <span>{{ end($a['bars'])['day'] ?? '' }}</span>
                </div>
            @endif
        </div>
    </div>
</div>
