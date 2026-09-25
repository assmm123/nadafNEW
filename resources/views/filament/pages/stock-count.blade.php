{{--
    الجرد الفعلي — عدّ المخزون وتسوية الفروقات.

    ── ما أُصلح هنا ──
    • زر «حفظ التسويات» كان بلا خلفية: `bg-primary-600` غير مُولَّد في بناء
      اللوحة (Filament يعرّف ألوانه في theme.css الغائب). فالإجراء الأساسي في
      الصفحة كان غير مرئي. أُصلح من جذره بإضافة جسر الألوان في admin.css.
    • لا إحصائيات إطلاقًا ⇒ شريط مؤشرات: أصناف معروضة · بها فرق · زيادة ·
      نقص · صافي · إجمالي الرصيد الدفتري.
    • لا مرشّح للفروقات ⇒ مرشّحات جاهزة (الكل · بها فرق · زيادة · نقص · مطابق).
    • `limit(200)` كان يقتطع بصمت ⇒ تنبيه صريح عند بلوغ السقف.
    • `apply()` بلا معاملة ⇒ معاملة واحدة حول الحلقة كلها.
    • لا تأكيد قبل الحفظ ⇒ `wire:confirm` يذكر عدد الفروقات.
    • لا وسيلة للتراجع ⇒ زر إرجاع لكل صنف + إرجاع الكل.
    • `wire:model.live` كان على كل صف بلا داعٍ ⇒ `.blur` فلا طلب لكل ضغطة.
--}}
<x-filament-panels::page>
    @php
        $stats = $this->stats();
        $rows = $this->visibleCounts();
        $pending = $stats['counted'];
    @endphp

    <div class="space-y-5">

        {{-- ① كيف يعمل الجرد --}}
        <div class="rounded-xl border border-warning-500/30 bg-warning-500/10 p-4">
            <p class="text-sm font-extrabold text-warning-400">كيف يعمل الجرد</p>
            <p class="mt-1 text-sm leading-relaxed text-gray-300">
                أدخل <b class="text-gray-100">العدّ الفعلي</b> لكل صنف بعد جرّه.
                الأصناف المطابقة لرصيدها الدفتري تُتجاهل تلقائيًا عند الحفظ،
                والفروقات تُسجَّل كحركات <b class="text-gray-100">«تسوية جرد»</b>
                في سجل حركات المخزون — فتبقى كل تسوية موثّقة بسببها.
            </p>
        </div>

        {{-- ② المؤشرات --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @php
                $cards = [
                    ['label' => 'أصناف معروضة', 'value' => number_format($stats['rows']), 'tone' => 'text-gray-100', 'note' => 'من '.number_format($this->totalVariants).' صنفًا'],
                    ['label' => 'بها فرق', 'value' => number_format($stats['counted']), 'tone' => $pending ? 'text-warning-400' : 'text-gray-100', 'note' => number_format($stats['untouched']).' مطابق'],
                    ['label' => 'زيادة (وحدات)', 'value' => '+'.number_format($stats['surplus']), 'tone' => $stats['surplus'] ? 'text-success-400' : 'text-gray-500', 'note' => 'وُجد أكثر من الدفتري'],
                    ['label' => 'نقص (وحدات)', 'value' => '−'.number_format($stats['shortage']), 'tone' => $stats['shortage'] ? 'text-danger-400' : 'text-gray-500', 'note' => 'أقل من الدفتري'],
                    ['label' => 'صافي الفرق', 'value' => ($stats['net'] > 0 ? '+' : '').number_format($stats['net']), 'tone' => $stats['net'] > 0 ? 'text-success-400' : ($stats['net'] < 0 ? 'text-danger-400' : 'text-gray-100'), 'note' => 'الزيادة ناقص النقص'],
                    ['label' => 'الرصيد الدفتري', 'value' => number_format($stats['units']), 'tone' => 'text-gray-100', 'note' => 'وحدة في الصفوف المعروضة'],
                ];
            @endphp

            @foreach ($cards as $card)
                <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
                    <p class="text-xs text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xl font-extrabold {{ $card['tone'] }}">{{ $card['value'] }}</p>
                    <p class="mt-0.5 text-[10px] text-gray-500">{{ $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ③ التنبيه عند الاقتطاع --}}
        @if ($this->truncated)
            <div class="flex items-start gap-2 rounded-xl border border-danger-500/40 bg-danger-500/10 p-4 text-sm text-gray-200">
                <span class="text-danger-400">⚠</span>
                <p>
                    يُعرض <b class="text-gray-100">{{ \App\Filament\Pages\StockCount::MAX_ROWS }} صنفًا فقط</b> من أصل
                    <b class="text-gray-100">{{ number_format($this->totalVariants) }}</b>.
                    ما لا تراه هنا <b class="text-gray-100">لا يُسوَّى</b>.
                    استخدم البحث لتقسيم الجرد إلى مجموعات، أو رشّح «بها فرق» بعد إدخال ما جردته.
                </p>
            </div>
        @endif

        {{-- ④ شريط الأدوات --}}
        <div class="rounded-xl border border-gray-700 bg-gray-800 p-3">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="ابحث بالاسم أو الرمز (SKU)…"
                       class="w-64 rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500">

                <div class="flex flex-wrap items-center gap-1">
                    @foreach ([
                        'all' => 'الكل',
                        'diff' => 'بها فرق',
                        'counted' => 'زيادة',
                        'shortage' => 'نقص',
                        'untouched' => 'مطابق',
                    ] as $value => $label)
                        <button type="button" wire:click="$set('filter', '{{ $value }}')"
                                class="rounded-lg border px-3 py-1.5 text-xs font-bold transition
                                    {{ $filter === $value
                                        ? 'border-primary-500 bg-primary-500/15 text-primary-400'
                                        : 'border-gray-600 bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="ms-auto flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="resetAll"
                            wire:confirm="سيُرجَع كل صنف معروض إلى رصيده الدفتري وتُلغى كل الإدخالات. متابعة؟"
                            class="rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-xs font-bold text-gray-200 transition hover:bg-gray-600">
                        ↺ إرجاع الكل للدفتري
                    </button>

                    <button type="button" wire:click="export"
                            class="rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-xs font-bold text-gray-200 transition hover:bg-gray-600">
                        ⇩ تصدير CSV
                    </button>

                    <button type="button" wire:click="apply" wire:loading.attr="disabled"
                            @if ($pending === 0) disabled @endif
                            @if ($pending > 0)
                                wire:confirm="سيُسجَّل تسوية جرد لـ {{ $pending }} صنفًا وتُحدَّث أرصدتها فورًا. متابعة؟"
                            @endif
                            class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-extrabold text-white transition
                                   hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-40">
                        <span wire:loading.remove wire:target="apply">
                            حفظ التسويات{{ $pending ? ' ('.$pending.')' : '' }}
                        </span>
                        <span wire:loading wire:target="apply">جارٍ الحفظ…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ⑤ الجدول --}}
        <div class="overflow-hidden rounded-xl border border-gray-700 bg-gray-800">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-700 bg-gray-900/60 text-xs text-gray-400">
                            <th class="p-3 text-start font-bold">الصنف</th>
                            <th class="p-3 text-center font-bold">الرصيد الدفتري</th>
                            <th class="p-3 text-center font-bold">العدّ الفعلي</th>
                            <th class="p-3 text-center font-bold">الفرق</th>
                            <th class="p-3 text-center font-bold">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $d = $this->diff($row); @endphp
                            <tr wire:key="cnt-{{ $row['id'] }}"
                                class="border-b border-gray-700/50 transition hover:bg-gray-700/30
                                       {{ $d !== 0 ? 'bg-warning-500/[0.06]' : '' }}">
                                <td class="p-3">
                                    <div class="font-bold text-gray-100">{{ $row['name'] }}</div>
                                    @if (filled($row['sku']))
                                        <div class="mt-0.5 font-mono text-[10px] text-gray-500" dir="ltr">{{ $row['sku'] }}</div>
                                    @endif
                                </td>

                                <td class="p-3 text-center font-bold text-gray-300">{{ number_format($row['book']) }}</td>

                                <td class="p-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <input type="number" min="0" inputmode="numeric"
                                               wire:model.blur="counts.{{ $row['i'] }}.actual"
                                               class="w-20 rounded-lg border border-gray-600 bg-gray-900 px-2 py-1.5 text-center text-gray-100
                                                      focus:border-primary-500 focus:ring-1 focus:ring-primary-500">

                                        <button type="button" wire:click="resetRow({{ $row['id'] }})"
                                                title="إرجاع هذا الصنف إلى رصيده الدفتري"
                                                class="rounded-lg border px-2 py-1.5 text-xs transition
                                                    {{ $d !== 0
                                                        ? 'border-gray-600 bg-gray-700 text-gray-200 hover:bg-gray-600'
                                                        : 'cursor-default border-transparent text-gray-600' }}">
                                            ↺
                                        </button>
                                    </div>
                                </td>

                                <td class="p-3 text-center">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold
                                        {{ $d > 0 ? 'bg-success-500/15 text-success-400'
                                           : ($d < 0 ? 'bg-danger-500/15 text-danger-400'
                                           : 'bg-gray-700 text-gray-400') }}">
                                        {{ $d > 0 ? '+' : '' }}{{ number_format($d) }}
                                    </span>
                                </td>

                                <td class="p-3 text-center text-xs">
                                    @if ($d > 0)
                                        <span class="font-bold text-success-400">زيادة</span>
                                    @elseif ($d < 0)
                                        <span class="font-bold text-danger-400">نقص</span>
                                    @else
                                        <span class="text-gray-500">مطابق</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-10 text-center text-sm text-gray-500">
                                    @if ($this->search !== '')
                                        لا نتائج للبحث «{{ $this->search }}»
                                    @elseif ($filter !== 'all')
                                        لا صنف في هذا المرشّح — جرّب «الكل»
                                    @else
                                        لا أصناف في المخزون بعد
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-[11px] text-gray-500">
            يُحسب الفرق تلقائيًا: <span class="text-gray-400">العدّ الفعلي − الرصيد الدفتري</span>.
            والأصناف المطابقة لا تُسجَّل عند الحفظ.
        </p>
    </div>
</x-filament-panels::page>
