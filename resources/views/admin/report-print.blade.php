<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير المبيعات — {{ $label }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Tajawal', 'Poppins', sans-serif; color: #1a2a3a; padding: 32px; max-width: 900px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #c9a84c; padding-bottom: 14px; }
        .logo { font-size: 24px; font-weight: 800; letter-spacing: 4px; }
        .logo small { display: block; font-size: 12px; letter-spacing: 0; color: #c9a84c; }
        h2 { font-size: 17px; margin: 18px 0 10px; color: #1a2a3a; }
        .period { font-size: 13px; color: #6b7a89; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
        .stat { background: #f8f5ef; border-radius: 8px; padding: 12px; }
        .stat b { display: block; font-size: 18px; color: #c9a84c; }
        .stat span { font-size: 11px; color: #6b7a89; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e4e9ef; padding: 8px 10px; text-align: start; font-size: 13px; }
        th { background: #f2f5f8; font-weight: 800; }
        .no-print { text-align: center; margin-bottom: 18px; }
        .no-print button { background: #1a2a3a; color: #fff; border: 0; padding: 10px 28px; border-radius: 8px; font-size: 14px; cursor: pointer; font-family: inherit; }
        .foot { margin-top: 24px; text-align: center; font-size: 11px; color: #6b7a89; border-top: 1px solid #e4e9ef; padding-top: 10px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button onclick="window.print()">🖨️ طباعة / حفظ كـ PDF</button>
    </div>

    <div class="head">
        <div class="logo">
            {{ strtoupper(setting('store_name_en', 'NADAF')) }}
            <small>{{ setting('store_name_ar', 'نداف') }} — تقرير المبيعات والأرباح</small>
        </div>
        <div class="period">{{ $label }}<br>{{ $from->format('Y/m/d') }} — {{ $to->format('Y/m/d') }}</div>
    </div>

    <div class="stats">
        <div class="stat"><b>{{ $summary['ordersCount'] }}</b><span>عدد الطلبات</span></div>
        <div class="stat"><b>${{ number_format($summary['salesUsd'], 2) }}</b><span>إجمالي المبيعات</span></div>
        <div class="stat"><b>${{ number_format($summary['profitUsd'], 2) }}</b><span>الأرباح المقدّرة</span></div>
        <div class="stat"><b>{{ number_format($summary['salesSyp']) }}</b><span>المبيعات بالليرة</span></div>
        <div class="stat"><b>{{ $summary['itemsSold'] }}</b><span>عناصر مبيعة</span></div>
        <div class="stat"><b>${{ number_format($summary['avgUsd'], 2) }}</b><span>متوسط قيمة الطلب</span></div>
    </div>

    <h2>السجل اليومي</h2>
    <table>
        <thead><tr><th>التاريخ</th><th>الطلبات</th><th>المبيعات $</th><th>المبيعات ل.س</th></tr></thead>
        <tbody>
            @forelse ($daily as $r)
                <tr><td>{{ $r['day'] }}</td><td>{{ $r['orders'] }}</td><td>${{ number_format($r['sales'], 2) }}</td><td>{{ number_format($r['sales_syp']) }}</td></tr>
            @empty
                <tr><td colspan="4">لا مبيعات</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>المبيعات حسب القسم</h2>
    <table>
        <thead><tr><th>القسم</th><th>الكمية</th><th>المبيعات $</th><th>الأرباح $</th></tr></thead>
        <tbody>
            @forelse ($byCategory as $r)
                <tr><td>{{ $r['name'] }}</td><td>{{ $r['qty'] }}</td><td>${{ number_format($r['sales'], 2) }}</td><td>${{ number_format($r['profit'], 2) }}</td></tr>
            @empty
                <tr><td colspan="4">لا مبيعات</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>أفضل المنتجات</h2>
    <table>
        <thead><tr><th>المنتج</th><th>الكمية</th><th>المبيعات $</th><th>الأرباح $</th></tr></thead>
        <tbody>
            @forelse ($byProduct as $r)
                <tr><td>{{ $r['name'] }}</td><td>{{ $r['qty'] }}</td><td>${{ number_format($r['sales'], 2) }}</td><td>${{ number_format($r['profit'], 2) }}</td></tr>
            @empty
                <tr><td colspan="4">لا مبيعات</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        تقرير مولّد تلقائيًا من لوحة تحكم متجر نداف — {{ now()->format('Y/m/d H:i') }}
    </div>
</body>
</html>
