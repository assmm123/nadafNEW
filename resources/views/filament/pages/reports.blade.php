{{--
    السجلات والأرباح — سجل المبيعات والأرباح.

    ── ما كان معطوبًا قبل هذه الجولة، وما أُصلح ──
    ١. الرسم البياني اليومي كان **فارغًا تمامًا**: أعمدة الرسم تستخدم
       `from-gold-600 to-gold-400`، وهذان الصنفان غير مُولَّدين في بناء اللوحة
       إطلاقًا — لأن navy/gold معرَّفتان في app.css الخاص بالمتجر، واللوحة
       تُحمِّل admin.css وحده. والرسم البياني كان يُعرض بإطار فارغ.
    ٢. أشرطة توزيع الأقسام كانت فارغة للسبب نفسه (`from-navy-900 to-gold-500`).
    ٣. أزرار التصدير كانت شفافة: `bg-navy-900` غير مُولَّد ⇒ نص أبيض على لا شيء.
    ٤. البطاقات كانت `bg-white` — أبيض حرفي غير معاد تعريفه — على لوحة داكنة،
       والنصوص فيها `text-gray-400` الفاتح ⇒ تباين ضعيف.
    ٥. زرّا «صورة PNG» و«HTML» **لا يعملان**، وكودهما يظهر نصًّا في الصفحة.
       والسبب دقيق: القالب النصّي في JS كان يحتوي `</body>` حرفيًا، وLivewire
       يحقن سكربته عند **أول** `</body>` في الردّ — فوقع الحقن داخل السكربت،
       و`</script>` الخاص به أغلق سكربتنا، فانهار الباقي وظهر نصًّا.
       العلاج هنا: كتابة `<\/body>` بالشرطة المهرَّبة (JS يقرأها `/` والـHTML
       لا يراها وسمًا) + تفويض الحدث على document فلا يضيع المستمع عند تحديث
       Livewire + حارس ضد الربط المزدوج.

    ── والجديد ──
    مقارنة بالفترة السابقة · أرباح يومية في الرسم والجدول · إبراز المؤشرات
    الناقصة (المختومة، البنود) · تحكم بعدد «أفضل المنتجات» · أزرار تصدير موحّدة.
--}}
<x-filament-panels::page>
    @php
        $deltas = $comparison['deltas'] ?? [];
        $previous = $comparison['previous'] ?? [];

        // الأرقام تُجمَّع في مصفوفة واحدة فيبقى القالب وصفًا لا حسابًا،
        // ويسهل تعديل ترتيب البطاقات أو إضافة مؤشر دون لمس بنية الصفحة.
        $cards = [
            [
                'label' => 'عدد الطلبات',
                'value' => number_format($summary['ordersCount'] ?? 0),
                'delta' => $deltas['ordersCount'] ?? null,
                'note' => number_format($summary['stampedCount'] ?? 0).' طلب مختوم',
                'tone' => 'primary',
            ],
            [
                'label' => 'إجمالي المبيعات',
                'value' => '$'.number_format($summary['salesUsd'] ?? 0, 2),
                'delta' => $deltas['salesUsd'] ?? null,
                'note' => number_format($summary['salesSyp'] ?? 0).' ل.س',
                'tone' => 'primary',
            ],
            [
                'label' => 'الأرباح المقدّرة',
                'value' => '$'.number_format($summary['profitUsd'] ?? 0, 2),
                'delta' => $deltas['profitUsd'] ?? null,
                'note' => 'من '.number_format($summary['itemsWithCost'] ?? 0).' بندًا معروفة التكلفة',
                'tone' => 'success',
            ],
            [
                'label' => 'متوسط قيمة الطلب',
                'value' => '$'.number_format($summary['avgUsd'] ?? 0, 2),
                'delta' => $deltas['avgUsd'] ?? null,
                'note' => 'لكل طلب غير ملغى',
                'tone' => 'info',
            ],
            [
                'label' => 'عناصر مبيعة',
                'value' => number_format($summary['itemsSold'] ?? 0),
                'delta' => $deltas['itemsSold'] ?? null,
                'note' => '$'.number_format($summary['itemsSalesUsd'] ?? 0, 2).' قيمة البنود',
                'tone' => 'info',
            ],
            [
                'label' => 'الفترة السابقة',
                'value' => '$'.number_format($previous['salesUsd'] ?? 0, 2),
                'delta' => null,
                'note' => isset($comparison['from'], $comparison['to'])
                    ? $comparison['from']->format('m/d').' — '.$comparison['to']->format('m/d')
                    : '—',
                'tone' => 'gray',
            ],
        ];

        $maxSales = max(1.0, (float) collect($daily)->max('sales'));
        $totalCategorySales = max(0.01, (float) collect($byCategory)->sum('sales'));
        $dayCount = count($daily);

        // ارتفاع الأعمدة بالبكسل لا بالنسبة المئوية: داخل حاوية flex لا يكون
        // ارتفاع النسبة مضمونًا في كل المتصفحات، والبكسل لا يخطئ.
        $chartMax = 150;
    @endphp

    <div class="space-y-6" id="report-capture" data-range="{{ $rangeLabel }}">

        {{-- ① الفترة --}}
        <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="report-period" class="mb-1.5 block text-xs font-bold text-gray-400">الفترة</label>
                    <select id="report-period" wire:model.live="period"
                            class="rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100">
                        @foreach ([
                            'today' => 'اليوم',
                            'yesterday' => 'أمس',
                            'last7' => 'آخر ٧ أيام',
                            'last30' => 'آخر ٣٠ يومًا',
                            'this_week' => 'هذا الأسبوع',
                            'last_week' => 'الأسبوع الماضي',
                            'this_month' => 'هذا الشهر',
                            'last_month' => 'الشهر الماضي',
                            'custom' => 'فترة مخصصة',
                        ] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($period === 'custom')
                    <div>
                        <label for="report-from" class="mb-1.5 block text-xs font-bold text-gray-400">من</label>
                        <input id="report-from" type="date" wire:model.live="from"
                               class="rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100">
                    </div>
                    <div>
                        <label for="report-to" class="mb-1.5 block text-xs font-bold text-gray-400">إلى</label>
                        <input id="report-to" type="date" wire:model.live="to"
                               class="rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100">
                    </div>
                @endif

                <div>
                    <label for="report-top" class="mb-1.5 block text-xs font-bold text-gray-400">عدد المنتجات في الجدول</label>
                    <select id="report-top" wire:model.live="topLimit"
                            class="rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100">
                        @foreach ([5, 10, 20, 50] as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ms-auto text-end">
                    <span class="block text-xs text-gray-500">الفترة المعروضة</span>
                    <span class="text-sm font-bold text-gray-200">{{ $rangeLabel }}</span>
                </div>
            </div>
        </div>

        {{-- ② المؤشرات --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($cards as $card)
                @php
                    $tone = $card['delta'] === null
                        ? 'flat'
                        : (abs($card['delta']) < 0.05 ? 'flat' : ($card['delta'] > 0 ? 'up' : 'down'));

                    $valueTone = match ($card['tone']) {
                        'success' => 'text-success-400',
                        'info' => 'text-info-400',
                        'primary' => 'text-primary-400',
                        default => 'text-gray-100',
                    };
                @endphp
                <div class="rounded-xl border border-gray-700 bg-gray-800 p-4">
                    <p class="text-xs text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xl font-extrabold {{ $valueTone }}">{{ $card['value'] }}</p>

                    <div class="mt-1.5 flex items-center gap-1.5">
                        @if ($card['delta'] !== null)
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold
                                {{ $tone === 'up' ? 'bg-success-500/15 text-success-400'
                                   : ($tone === 'down' ? 'bg-danger-500/15 text-danger-400'
                                   : 'bg-gray-700 text-gray-400') }}">
                                {{ $tone === 'up' ? '▲' : ($tone === 'down' ? '▼' : '■') }}
                                {{ \App\Filament\Pages\Reports::deltaLabel($card['delta']) }}
                            </span>
                        @elseif ($card['label'] !== 'الفترة السابقة')
                            {{-- «+100%» حين تكون الفترة السابقة صفرًا رقم كاذب --}}
                            <span class="rounded-full bg-gray-700 px-1.5 py-0.5 text-[10px] text-gray-500">
                                لا فترة سابقة للمقارنة
                            </span>
                        @endif
                        <span class="text-[10px] text-gray-500">{{ $card['note'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ③ التصدير — نمط واحد لكل الأزرار، والأيقونة وحدها تحمل التمييز --}}
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-700 bg-gray-800 p-3">
            <span class="text-xs font-bold text-gray-400">تصدير التقرير</span>

            @php
                $exportQuery = ['period' => $period, 'from' => $from, 'to' => $to];
                $exportBtn = 'inline-flex items-center gap-1.5 rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-xs font-bold text-gray-100 transition hover:border-gray-500 hover:bg-gray-600';
            @endphp

            <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'csv']) }}" class="{{ $exportBtn }}">
                <span class="text-primary-400">▤</span> CSV
            </a>
            <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'xlsx']) }}" class="{{ $exportBtn }}">
                <span class="text-success-400">▦</span> Excel
            </a>
            <a href="{{ route('admin.reports.export', $exportQuery + ['type' => 'pdf']) }}" target="_blank" rel="noopener" class="{{ $exportBtn }}">
                <span class="text-danger-400">▣</span> PDF
            </a>
            <button type="button" id="report-export-image" class="{{ $exportBtn }}">
                <span class="text-warning-400">▩</span> صورة PNG
            </button>
            <button type="button" id="report-export-html" class="{{ $exportBtn }}">
                <span class="text-info-400">◫</span> HTML
            </button>

            <span class="ms-auto text-[10px] text-gray-500">الصورة و HTML تُصدَّران ما تراه الآن على الشاشة</span>
        </div>

        {{-- ④ الرسوم --}}
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-xl border border-gray-700 bg-gray-800 p-5 xl:col-span-2">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-extrabold text-gray-100">المبيعات والأرباح اليومية</h3>
                    <div class="flex items-center gap-3 text-[11px] text-gray-400">
                        <span class="flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-sm bg-primary-500"></i> المبيعات</span>
                        <span class="flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-sm bg-success-500"></i> الأرباح</span>
                        <span class="text-gray-500">{{ $dayCount }} يومًا</span>
                    </div>
                </div>

                @if ($dayCount)
                    <div class="relative">
                        {{-- خطوط الشبكة خلف الأعمدة --}}
                        <div class="pointer-events-none absolute inset-x-0 top-0" style="height: {{ $chartMax }}px">
                            @foreach ([0, 0.5, 1] as $g)
                                <div class="absolute inset-x-0 border-t border-dashed border-gray-700/70" style="top: {{ $g * 100 }}%"></div>
                            @endforeach
                        </div>

                        <div class="relative flex items-end gap-1.5 overflow-x-auto pb-1">
                            @foreach ($daily as $d)
                                @php
                                    $hSales = max(3, (int) round($d['sales'] / $maxSales * $chartMax));
                                    $hProfit = max(0, (int) round($d['profit'] / $maxSales * $chartMax));
                                @endphp
                                <div class="group flex min-w-[34px] flex-1 flex-col items-center justify-end"
                                     title="{{ $d['day'] }} — مبيعات ${{ number_format($d['sales'], 2) }} · أرباح ${{ number_format($d['profit'], 2) }} · {{ $d['orders'] }} طلب">
                                    <div class="flex w-full items-end justify-center gap-0.5" style="height: {{ $chartMax }}px">
                                        <div class="w-1/2 rounded-t bg-primary-500 transition group-hover:bg-primary-400"
                                             style="height: {{ $hSales }}px"></div>
                                        <div class="w-1/2 rounded-t bg-success-500 transition group-hover:bg-success-400"
                                             style="height: {{ $hProfit }}px"></div>
                                    </div>
                                    <span class="mt-1.5 text-[9px] text-gray-500">{{ substr($d['day'], 5) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-2 text-[10px] text-gray-500">أعلى يوم: ${{ number_format($maxSales, 2) }} — مرّر المؤشر على أي عمود لتفاصيله</p>
                    </div>
                @else
                    <p class="py-12 text-center text-sm text-gray-500">لا مبيعات في هذه الفترة</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-700 bg-gray-800 p-5">
                <h3 class="mb-4 font-extrabold text-gray-100">توزيع الأقسام</h3>
                @if (count($byCategory))
                    <div class="space-y-3.5">
                        @foreach ($byCategory as $cat)
                            @php $pct = round($cat['sales'] / $totalCategorySales * 100); @endphp
                            <div>
                                <div class="mb-1 flex items-baseline justify-between gap-2 text-xs">
                                    <span class="truncate font-bold text-gray-200">{{ $cat['name'] }}</span>
                                    <span class="shrink-0 text-gray-400" dir="ltr">{{ $pct }}% · ${{ number_format($cat['sales'], 2) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full bg-primary-500" style="width: {{ max($pct, 2) }}%"></div>
                                </div>
                                <div class="mt-0.5 flex justify-between text-[10px] text-gray-500">
                                    <span>{{ $cat['qty'] }} قطعة</span>
                                    <span>ربح ${{ number_format($cat['profit'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="py-12 text-center text-sm text-gray-500">لا بيانات</p>
                @endif
            </div>
        </div>

        {{-- ⑤ الجداول --}}
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-700 bg-gray-800 p-5">
                <h3 class="mb-3 font-extrabold text-gray-100">المبيعات حسب القسم</h3>
                @if (count($byCategory))
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700 text-xs text-gray-400">
                                <th class="py-2 text-start font-bold">القسم</th>
                                <th class="py-2 text-center font-bold">الكمية</th>
                                <th class="py-2 text-center font-bold">المبيعات</th>
                                <th class="py-2 text-center font-bold">الأرباح</th>
                                <th class="py-2 text-center font-bold">الحصة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byCategory as $row)
                                @php $share = round($row['sales'] / $totalCategorySales * 100); @endphp
                                <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                                    <td class="py-2 font-bold text-gray-200">{{ $row['name'] }}</td>
                                    <td class="py-2 text-center text-gray-300">{{ number_format($row['qty']) }}</td>
                                    <td class="py-2 text-center font-bold text-primary-400">${{ number_format($row['sales'], 2) }}</td>
                                    <td class="py-2 text-center font-bold text-success-400">${{ number_format($row['profit'], 2) }}</td>
                                    <td class="py-2">
                                        <div class="mx-auto flex w-16 items-center gap-1.5">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-700">
                                                <div class="h-full rounded-full bg-primary-500" style="width: {{ max($share, 2) }}%"></div>
                                            </div>
                                            <span class="text-[10px] text-gray-400">{{ $share }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="py-8 text-center text-sm text-gray-500">لا مبيعات في هذه الفترة</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-700 bg-gray-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="font-extrabold text-gray-100">أفضل المنتجات</h3>
                    @if (count($byProduct))
                        <span class="rounded-full bg-gray-700 px-2 py-0.5 text-[10px] text-gray-300">
                            {{ count($byProduct) }} صفًا · مرتّبة بالمبيعات
                        </span>
                    @endif
                </div>
                @if (count($byProduct))
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700 text-xs text-gray-400">
                                <th class="py-2 text-start font-bold">المنتج</th>
                                <th class="py-2 text-center font-bold">الكمية</th>
                                <th class="py-2 text-center font-bold">المبيعات</th>
                                <th class="py-2 text-center font-bold">الربح</th>
                                <th class="py-2 text-center font-bold">الهامش</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byProduct as $row)
                                @php $margin = $row['sales'] > 0 ? round($row['profit'] / $row['sales'] * 100) : 0; @endphp
                                <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                                    <td class="py-2 font-bold text-gray-200">{{ $row['name'] }}</td>
                                    <td class="py-2 text-center text-gray-300">{{ number_format($row['qty']) }}</td>
                                    <td class="py-2 text-center font-bold text-primary-400">${{ number_format($row['sales'], 2) }}</td>
                                    <td class="py-2 text-center font-bold text-success-400">${{ number_format($row['profit'], 2) }}</td>
                                    <td class="py-2 text-center">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-bold
                                            {{ $margin >= 30 ? 'bg-success-500/15 text-success-400'
                                               : ($margin >= 15 ? 'bg-warning-500/15 text-warning-400'
                                               : 'bg-danger-500/15 text-danger-400') }}">{{ $margin }}%</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="py-8 text-center text-sm text-gray-500">لا مبيعات في هذه الفترة</p>
                @endif
            </div>
        </div>

        {{-- ⑥ السجل اليومي --}}
        <div class="rounded-xl border border-gray-700 bg-gray-800 p-5">
            <h3 class="mb-3 font-extrabold text-gray-100">السجل اليومي</h3>
            @if (count($daily))
                @php
                    $sumOrders = collect($daily)->sum('orders');
                    $sumSales = collect($daily)->sum('sales');
                    $sumProfit = collect($daily)->sum('profit');
                    $sumItems = collect($daily)->sum('items');
                @endphp
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700 text-xs text-gray-400">
                                <th class="py-2 text-start font-bold">التاريخ</th>
                                <th class="py-2 text-center font-bold">الطلبات</th>
                                <th class="py-2 text-center font-bold">البنود</th>
                                <th class="py-2 text-center font-bold">المبيعات $</th>
                                <th class="py-2 text-center font-bold">الأرباح $</th>
                                <th class="py-2 text-center font-bold">المبيعات ل.س</th>
                                <th class="py-2 text-center font-bold">الهامش</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daily as $row)
                                @php $rowMargin = $row['sales'] > 0 ? round($row['profit'] / $row['sales'] * 100) : 0; @endphp
                                <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                                    <td class="py-2 font-bold text-gray-200" dir="ltr">{{ $row['day'] }}</td>
                                    <td class="py-2 text-center text-gray-300">{{ number_format($row['orders']) }}</td>
                                    <td class="py-2 text-center text-gray-400">{{ number_format($row['items']) }}</td>
                                    <td class="py-2 text-center font-bold text-primary-400">${{ number_format($row['sales'], 2) }}</td>
                                    <td class="py-2 text-center font-bold text-success-400">${{ number_format($row['profit'], 2) }}</td>
                                    <td class="py-2 text-center text-gray-400">{{ number_format($row['sales_syp']) }}</td>
                                    <td class="py-2 text-center text-xs text-gray-400">{{ $rowMargin }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-600 text-sm font-extrabold text-gray-100">
                                <td class="py-2.5">الإجمالي ({{ $dayCount }} يومًا)</td>
                                <td class="py-2.5 text-center">{{ number_format($sumOrders) }}</td>
                                <td class="py-2.5 text-center text-gray-300">{{ number_format($sumItems) }}</td>
                                <td class="py-2.5 text-center text-primary-400">${{ number_format($sumSales, 2) }}</td>
                                <td class="py-2.5 text-center text-success-400">${{ number_format($sumProfit, 2) }}</td>
                                <td class="py-2.5 text-center text-gray-400">{{ number_format(collect($daily)->sum('sales_syp')) }}</td>
                                <td class="py-2.5 text-center text-xs text-gray-400">
                                    {{ $sumSales > 0 ? round($sumProfit / $sumSales * 100) : 0 }}%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="py-8 text-center text-sm text-gray-500">لا مبيعات في هذه الفترة</p>
            @endif
        </div>
    </div>

    {{-- تصدير الصورة و HTML — يعملان على كامل التقرير المعروض --}}
    <script src="{{ asset('js/html2canvas.min.js') }}"></script>
    <script>
        (function () {
            // Livewire قد يعيد تنفيذ السكربت بعد كل تحديث للفترة، وهذا الحارس
            // يمنع تسجيل المستمع مرتين فتقع كل نقرة مرتين.
            if (window.__nadReportExportBound) return;
            window.__nadReportExportBound = true;

            const safeName = (ext) => {
                const range = document.getElementById('report-capture')?.dataset.range || 'تقرير';
                return 'تقرير-' + range.replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '') + '.' + ext;
            };

            const download = (href, name) => {
                const a = document.createElement('a');
                a.download = name;
                a.href = href;
                a.click();
            };

            // تفويض الحدث على document لا ربطًا مباشرًا بالزر: Livewire يستبدل
            // DOM عند تغيير الفترة، فلو رُبط الزر مرة واحدة لضاع المستمع وصار
            // الزر صامتًا بلا أي خطأ ظاهر.
            document.addEventListener('click', async function (event) {
                const capture = document.getElementById('report-capture');
                if (!capture) return;

                const imageBtn = event.target.closest('#report-export-image');

                if (imageBtn) {
                    const original = imageBtn.innerHTML;
                    imageBtn.disabled = true;
                    imageBtn.textContent = 'جارٍ التجهيز…';

                    try {
                        const canvas = await html2canvas(capture, { scale: 2, backgroundColor: '#0F1319', useCORS: true });
                        download(canvas.toDataURL('image/png'), safeName('png'));
                    } catch (e) {
                        alert('تعذّر إنشاء الصورة');
                    }

                    imageBtn.disabled = false;
                    imageBtn.innerHTML = original;
                    return;
                }

                if (event.target.closest('#report-export-html')) {
                    // ⚠️ قاعدة حاكمة: لا يُكتب التسلسل الحرفي لوسم الإغلاق
                    // (شرطة مائلة + اسم الوسم) في أي مكان داخل هذا السكربت —
                    // لا في نصّ ولا في تعليق. وقد وقع هذا الخطأ مرّتين:
                    //   ١. محرّك HTML ينهي السكربت عند أول وسم إغلاق لـscript،
                    //      فما بعده يصير نصًّا ظاهرًا في الصفحة.
                    //   ٢. وLivewire يحقن سكربته عند **أول** وسم إغلاق لـbody
                    //      في الردّ؛ فإن كان داخل سكربتنا وقع الحقن في وسطه،
                    //      ووسم الإغلاق الخاص به أنهى سكربتنا من حيث لا ندري.
                    // والعلاج: كتابة الوسمين بالشرطة المهرَّبة في النصوص،
                    // ووصفهما بالكلام لا بالرموز في التعليقات.
                    const styles = Array.from(document.querySelectorAll('style')).map(s => s.outerHTML).join('\n');
                    const parts = [
                        '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">',
                        '<title>' + safeName('html') + '</title>',
                        styles,
                        '<\/head><body>',
                        capture.outerHTML,
                        '<\/body><\/html>',
                    ];

                    const blob = new Blob([parts.join('')], { type: 'text/html;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    download(url, safeName('html'));
                    URL.revokeObjectURL(url);
                }
            });
        })();
    </script>
</x-filament-panels::page>
