<!-- لوحة التحكم المحاسبية — منقول من NADAF-Dashboard-Concept.html -->
@php
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d);
    $syp = fn ($v) => number_format((float) $v, 0);

    $statusMeta = fn (string $s) => match ($s) {
        'pending' => ['بانتظار التأكيد', '#E8B98A'],
        'preparing' => ['قيد التحضير', '#EAC97F'],
        'shipped' => ['مشحون', '#9DC0DC'],
        'delivered' => ['مسلَّم', '#7FD3A2'],
        'cancelled' => ['ملغى', '#E9A3B2'],
        default => [\App\Models\Order::statusLabel($s), '#8A93A3'],
    };

    $bars = $accounting['chart']['bars'] ?? [];
    $axis = $accounting['chart']['axis'] ?? [];
    $avgY = 172 - (($accounting['chart']['averageRatio'] ?? 0) * 162);
@endphp

<div class="nad-dash">

    <!-- شريط الأدوات -->
    <div class="top">
        <div>
            <h1>لوحة التحكم</h1>
            <div class="sub">{{ now()->translatedFormat('l j F Y') }} · {{ now()->format('H:i') }}</div>
        </div>

        <div class="seg" role="group" aria-label="الفترة">
            <button type="button" aria-pressed="false">اليوم</button>
            <button type="button" aria-pressed="false">الأسبوع</button>
            <button type="button" aria-pressed="true">هذا الشهر</button>
            <button type="button" aria-pressed="false">مخصّص</button>
        </div>

        <div class="tools">
            <a href="{{ route('admin.reports.export', ['period' => 'this_month', 'type' => 'xlsx']) }}" class="tool solid">تصدير Excel</a>
            <a href="{{ route('admin.reports.export', ['period' => 'this_month', 'type' => 'pdf']) }}" target="_blank" class="tool">تقرير PDF</a>
            <button type="button" class="tool" onclick="window.print()">طباعة</button>
            <button type="button" class="tool" onclick="window.location.reload()">تحديث</button>
        </div>
    </div>

    <!-- ═══ ١) يحتاج إجراءك الآن ═══ -->
    <div class="sh">
        <span class="k">Action</span>
        <h2>يحتاج إجراءك الآن</h2>
        <span class="n">اضغط أي بطاقة لتفتح القائمة مفلترة</span>
    </div>

    <div class="acts">
        @foreach ($actions as $card)
            @php $zero = $card['value'] === 0; @endphp
            @if ($card['url'] && ! $zero)
                <a class="act" href="{{ $card['url'] }}">
                    <span class="v n" style="color:{{ $card['color'] }}">{{ $card['value'] }}</span>
                    <span class="l">{{ $card['label'] }}</span>
                    <span class="h">{{ $card['hint'] }}</span>
                </a>
            @else
                <div class="act {{ $zero ? 'zero' : '' }}">
                    <span class="v n" @if (! $zero) style="color:{{ $card['color'] }}" @endif>{{ $card['value'] }}</span>
                    <span class="l">{{ $card['label'] }}</span>
                    <span class="h">{{ $card['hint'] }}</span>
                </div>
            @endif
        @endforeach
    </div>

    @if ($accounting)
        <!-- ═══ ٢) المحاسبة — هذا الشهر ═══ -->
        <div class="sh">
            <span class="k">Accounting</span>
            <h2>المحاسبة — هذا الشهر</h2>
            <span class="n">{{ $accounting['period'] }}</span>
        </div>

        <div class="acc">
            <!-- دفتر الأرقام -->
            <div class="panel">
                <div class="panel-h">
                    قائمة الدخل المبسّطة
                    <span class="r">
                        <a href="{{ route('admin.reports.export', ['period' => 'this_month', 'type' => 'xlsx']) }}" class="tool">Excel</a>
                        <a href="{{ route('admin.reports.export', ['period' => 'this_month', 'type' => 'pdf']) }}" target="_blank" class="tool">PDF</a>
                    </span>
                </div>
                <table class="ledger n">
                    <tr class="grp"><td colspan="2">الإيرادات</td></tr>
                    <tr><td>المبيعات الإجمالية</td><td>{{ $fmt($accounting['gross']) }} $</td></tr>
                    <tr><td>الخصومات</td><td class="neg">− {{ $fmt($accounting['discounts']) }} $</td></tr>
                    <tr><td>الشحن المحصّل</td><td>{{ $fmt($accounting['shipping']) }} $</td></tr>
                    <tr class="sum"><td>صافي الإيرادات</td><td>{{ $fmt($accounting['netRevenue']) }} $</td></tr>

                    <tr class="grp"><td colspan="2">التكاليف</td></tr>
                    <tr><td>تكلفة البضاعة المبيعة</td><td class="neg">− {{ $fmt($accounting['cogs']) }} $</td></tr>
                    <tr class="total"><td>الربح الإجمالي</td><td>{{ $fmt($accounting['profit']) }} $</td></tr>

                    <tr class="grp"><td colspan="2">المؤشرات</td></tr>
                    <tr><td>هامش الربح الإجمالي</td><td class="pct">{{ $accounting['margin'] }}%</td></tr>
                    <tr><td>عدد الطلبات</td><td>{{ $accounting['orders'] }}</td></tr>
                    <tr><td>متوسط قيمة الطلب</td><td>{{ $fmt($accounting['avgOrder']) }} $</td></tr>
                    <tr><td>القطع المبيعة</td><td>{{ $accounting['items'] }}</td></tr>
                </table>
            </div>

            <!-- الرسم البياني -->
            <div class="panel">
                <div class="panel-h">
                    المبيعات اليومية
                    <span class="r"><span style="font-size:11px;color:#8A93A3">آخر ١٤ يومًا</span></span>
                </div>

                <div class="chart">
                    <svg viewBox="0 0 620 200" preserveAspectRatio="none" role="img" aria-label="المبيعات اليومية لآخر 14 يومًا">
                        <!-- شبكة أفقية -->
                        <g stroke="rgba(255,255,255,.07)" stroke-width="1">
                            <line x1="46" y1="10" x2="612" y2="10"/>
                            <line x1="46" y1="52" x2="612" y2="52"/>
                            <line x1="46" y1="94" x2="612" y2="94"/>
                            <line x1="46" y1="136" x2="612" y2="136"/>
                            <line x1="46" y1="172" x2="612" y2="172" stroke="rgba(255,255,255,.16)"/>
                        </g>

                        <!-- محور القيم -->
                        <g fill="#8A93A3" font-size="10" font-family="Tajawal" text-anchor="end">
                            @foreach ([10, 52, 94, 136, 172] as $i => $y)
                                <text x="38" y="{{ $y + 4 }}">{{ number_format($axis[$i] ?? 0) }}</text>
                            @endforeach
                        </g>

                        <!-- الأعمدة -->
                        @foreach ($bars as $i => $bar)
                            @php
                                $h = max(0, $bar['ratio'] * 162);
                                $last = $i === count($bars) - 1;
                            @endphp
                            <rect x="{{ 54 + ($i * 40) }}" y="{{ 172 - $h }}" width="30" height="{{ round($h, 1) }}" rx="2"
                                  fill="{{ $last ? '#D2A24E' : 'rgba(210,162,78,.34)' }}">
                                <title>{{ $bar['label'] }} — {{ $fmt($bar['sales']) }} $</title>
                            </rect>
                        @endforeach

                        <!-- خط المتوسط -->
                        <line x1="46" y1="{{ round($avgY, 1) }}" x2="612" y2="{{ round($avgY, 1) }}"
                              stroke="#9DC0DC" stroke-width="1" stroke-dasharray="4 4"/>
                        <text x="608" y="{{ round($avgY - 4, 1) }}" fill="#9DC0DC" font-size="9.5" font-family="Tajawal" text-anchor="end">المتوسط</text>
                    </svg>

                    <div class="xlab">
                        @foreach ([0, 3, 6, 9, 13] as $i)
                            <span>{{ $bars[$i]['label'] ?? '' }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="legend">
                    <span><i style="background:#D2A24E"></i>اليوم</span>
                    <span><i style="background:rgba(210,162,78,.34)"></i>الأيام السابقة</span>
                    <span><i style="background:#9DC0DC"></i>المتوسط اليومي</span>
                </div>
            </div>
        </div>
    @endif

    @if ($position)
        <!-- ═══ ٣) المركز المالي — اليوم ═══ -->
        <div class="sh">
            <span class="k">Position</span>
            <h2>المركز المالي — اليوم</h2>
            <span class="n">بالليرة السورية</span>
        </div>

        <div class="fin">
            <div class="panel">
                <div class="panel-h">الأصول <span>ما لك</span></div>
                <table class="n">
                    <tr><td>مقبوض اليوم</td><td style="color:#7FD3A2">{{ $syp($position['cash']) }}</td></tr>
                    <tr><td>ذمم مدينة — طلبات غير مقبوضة</td><td style="color:#EAC97F">{{ $syp($position['receivable']) }}</td></tr>
                    <tr><td>قيمة المخزون بالتكلفة</td><td>{{ $syp($position['inventory']) }}</td></tr>
                    <tr class="net"><td>إجمالي الأصول</td><td>{{ $syp($position['assetsTotal']) }}</td></tr>
                </table>
            </div>

            <div class="panel">
                <div class="panel-h">الالتزامات <span>ما عليك</span></div>
                <table class="n">
                    <tr><td>ذمم دائنة — فواتير شراء مؤكدة</td><td style="color:#E9A3B2">{{ $syp($position['payable']) }}</td></tr>
                    <tr><td>فواتير شراء مسودة (لم تُعتمد)</td><td style="color:#8A93A3">—</td></tr>
                    <tr><td>طلبات ملغاة تنتظر إعادة المبلغ</td><td style="color:#8A93A3">—</td></tr>
                    <tr class="net"><td>إجمالي الالتزامات</td><td>{{ $syp($position['liabilitiesTotal']) }}</td></tr>
                </table>
                <div class="eq n">
                    صافي المركز النقدي = {{ $syp($position['cash']) }} + {{ $syp($position['receivable']) }} − {{ $syp($position['payable']) }}
                    = <b style="color:#EAC97F">{{ $syp($position['net']) }} ل.س</b>
                </div>
            </div>
        </div>
    @endif

    @if ($canOrders)
        <!-- ═══ ٤) أحدث الطلبات ═══ -->
        <div class="sh">
            <span class="k">Orders</span>
            <h2>أحدث الطلبات</h2>
            <a href="{{ \App\Filament\Resources\OrderResource::getUrl('index') }}" class="n" style="color:#EAC97F;font-weight:700">كل الطلبات ←</a>
        </div>

        <div class="panel" style="overflow:hidden">
            <table class="tbl n">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>العميل</th>
                        <th>الحالة</th>
                        <th>الدفع</th>
                        <th>التوثيق</th>
                        <th style="text-align:end">الإجمالي</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php [$stLabel, $stColor] = $statusMeta($order->status); @endphp
                        <tr onclick="window.location='{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $order]) }}'" style="cursor:pointer">
                            <td class="code">{{ $order->order_code }}</td>
                            <td>{{ $order->customerName() }}</td>
                            <td>
                                <span class="st" style="color:{{ $stColor }}">
                                    <i style="background:currentColor"></i>{{ $stLabel }}
                                </span>
                            </td>
                            <td>
                                @if ($order->payment_confirmed_at)
                                    <span class="pay" style="color:#7FD3A2">✓ مقبوض</span>
                                @else
                                    <span class="pay" style="color:#8A93A3">○ غير مقبوض</span>
                                @endif
                            </td>
                            <td>
                                @if ($order->status === 'delivered')
                                    <span style="color:#EAC97F">◆ تم التسليم</span>
                                @else
                                    <span style="color:#8A93A3">—</span>
                                @endif
                            </td>
                            <td class="amt">{{ $fmt($order->total_usd, 0) }}</td>
                            <td style="color:#8A93A3">{{ $order->created_at?->format('d/m · H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:#8A93A3">لا طلبات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

</div>
