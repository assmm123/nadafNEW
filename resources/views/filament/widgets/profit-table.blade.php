<div class="nad-ocard !p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-display text-lg text-nad-champ">
            <span class="dia">◆</span> أرباح آخر 30 يومًا — مرتبة بالربح
        </h3>
        <button wire:click="exportExcel" class="nad-btn-ghost !px-4 !py-2 !text-xs">
            📊 تصدير Excel
        </button>
    </div>

    @if (count($rows))
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-nad-line2 text-start text-[11px] text-nad-mut">
                    <th class="py-2 text-start">المنتج</th>
                    <th class="py-2 text-center">بيعت</th>
                    <th class="py-2 text-center">المبيعات</th>
                    <th class="py-2 text-center">التكلفة</th>
                    <th class="py-2 text-center">صافي الربح</th>
                    <th class="py-2 text-center">الهامش</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr class="border-b border-nad-line2 transition hover:bg-nad-surface2/50">
                        <td class="py-2.5 font-bold text-nad-ivory">{{ $r['name'] }}</td>
                        <td class="py-2.5 text-center text-nad-mut">{{ $r['qty'] }}</td>
                        <td class="py-2.5 text-center font-bold text-nad-brass">${{ number_format($r['sales'], 2) }}</td>
                        <td class="py-2.5 text-center text-nad-mut">${{ number_format($r['cost'], 2) }}</td>
                        <td class="py-2.5 text-center font-extrabold text-green-400">${{ number_format($r['profit'], 2) }}</td>
                        <td class="py-2.5 text-center">
                            <span class="nad-chip {{ $r['margin'] >= 30 ? 'nad-chip-ok' : ($r['margin'] >= 15 ? 'nad-chip-wr' : 'nad-chip-ox') }}">
                                {{ $r['margin'] }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="border-t-2 border-nad-brass/40 font-extrabold">
                    <td class="py-3 text-nad-ivory">الإجمالي</td>
                    <td></td>
                    <td class="py-3 text-center text-nad-brass">${{ number_format($totals['sales'], 2) }}</td>
                    <td class="py-3 text-center text-nad-mut">${{ number_format($totals['cost'], 2) }}</td>
                    <td class="py-3 text-center text-green-400">${{ number_format($totals['profit'], 2) }}</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="py-10 text-center text-sm text-nad-mut">لا مبيعات في آخر 30 يومًا</div>
    @endif
</div>
