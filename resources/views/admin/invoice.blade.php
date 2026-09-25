<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $order->order_code }} — {{ $order->stamped_at ? 'فاتورة نظامية معتمدة' : 'إيصال طلب' }}</title>
{{ \Illuminate\Support\Facades\Vite::fonts() }}
<style>
/* ══════════════════════════════════════════════════════════════
   الفاتورة — بالهوية الداكنة نفسها (فحمي #0F1319 · سطح #171D26 · نحاسي #D2A24E)
   ══════════════════════════════════════════════════════════════ */
:root{
  --bg:#0F1319; --sf:#171D26; --sf2:#1B222D; --sf3:#222A36;
  --line:rgba(255,255,255,.08); --line2:rgba(255,255,255,.14);
  --brass:#D2A24E; --champ:#EAC97F;
  --ivory:#F1ECE1; --mut:#A79F8F; --dim:#8A93A3;
  --ok:#7FD3A2; --err:#E9A3B2;
  --f-d:'El Messiri','Almarai',sans-serif; --f-b:'Tajawal',system-ui,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ivory);font-family:var(--f-b);font-size:13px;line-height:1.7;
  padding:22px 20px 50px;-webkit-font-smoothing:antialiased}
.n{font-variant-numeric:tabular-nums;font-feature-settings:'tnum'}

.toolbar{max-width:820px;margin:0 auto 16px;display:flex;gap:8px;flex-wrap:wrap}
.btn{padding:9px 18px;font-size:12.5px;font-weight:700;border-radius:7px;border:1px solid var(--line2);
  color:var(--mut);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px;transition:.15s}
.btn:hover{border-color:var(--brass);color:var(--champ)}
.btn.brass{background:var(--brass);color:#17110A;border-color:var(--brass)}
.btn.brass:hover{background:var(--champ)}
.btn.wa{border-color:rgba(37,211,102,.5);color:#25D366}
.btn.wa:hover{background:rgba(37,211,102,.1)}

.inv{max-width:820px;margin:0 auto;background:var(--sf);border:1px solid rgba(210,162,78,.28);
  border-radius:12px;overflow:hidden}
.inv-hd{display:flex;align-items:flex-start;gap:16px;padding:24px 26px 20px;border-bottom:2px solid var(--brass)}
.logo{width:50px;height:50px;border-radius:11px;background:var(--brass);color:#17110A;
  display:grid;place-items:center;font-family:var(--f-d);font-size:22px;font-weight:700;flex:none}
.store b{display:block;font-family:var(--f-d);font-size:19px;line-height:1.2}
.store span{display:block;font-size:11px;color:var(--dim);margin-top:2px}
.doc{margin-inline-start:auto;text-align:end}
.doc .t{display:inline-block;font-family:var(--f-d);font-size:10.5px;font-weight:700;letter-spacing:.1em;
  text-transform:uppercase;padding:5px 12px;border-radius:5px;margin-bottom:7px}
.doc .t.draft{background:var(--sf3);color:var(--mut);border:1px dashed var(--line2)}
.doc .t.cert{background:var(--brass);color:#17110A}
.doc .code{font-family:ui-monospace,monospace;font-size:13px;color:var(--champ);direction:ltr}
.doc .date{font-size:11px;color:var(--dim);margin-top:2px}

.meta{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--line)}
.meta>div{padding:16px 26px}
.meta>div:first-child{border-inline-end:1px solid var(--line)}
.meta h4{font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--dim);margin-bottom:8px}
.meta p{font-size:12.5px;color:var(--mut);line-height:1.8}
.meta b{color:var(--ivory);font-weight:500}

table.items{width:100%;border-collapse:collapse}
table.items th{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);
  text-align:start;padding:10px 12px;border-bottom:1px solid var(--line2);background:var(--sf2)}
table.items th:first-child,table.items td:first-child{padding-inline-start:26px}
table.items th:last-child,table.items td:last-child{padding-inline-end:26px;text-align:end}
table.items td{padding:11px 12px;font-size:12.5px;border-bottom:1px solid var(--line);color:var(--mut)}
table.items td:first-child{color:var(--ivory)}
table.items td.num{text-align:end;color:var(--ivory);font-weight:500}

.tot{padding:16px 26px;border-top:1px solid var(--line)}
.tot div{display:flex;justify-content:space-between;font-size:12.5px;padding:5px 0;color:var(--mut)}
.tot b{color:var(--ivory);font-weight:500}
.tot .grand{border-top:2px solid var(--brass);margin-top:8px;padding-top:11px;align-items:baseline}
.tot .grand span{color:var(--ivory);font-weight:500}
.tot .grand b{font-family:var(--f-d);font-size:22px;color:var(--champ)}
.tot .fx{font-size:11px;color:var(--dim);padding-top:5px}

.stamps{padding:18px 26px 22px;border-top:1px solid var(--line);background:rgba(210,162,78,.03)}
.stamps h4{font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--dim);margin-bottom:13px}
.srow{display:grid;grid-template-columns:1fr 1fr;gap:14px}
/* الختم المرفوع صورةً: يُعرض **وحده بلا أي مربع أو إطار**.
   الختم نفسه يحمل إطاره المرسوم؛ فإحاطته بمربع آخر تشويش لا توثيق.
   ويسقط معه التدوير الطفيف — التدوير يليق بختم مرسوم لا بصورة حقيقية. */
.stp{border:0;background:none;padding:0;transform:none;text-align:center}
.stp-img{display:block;width:auto;max-width:100%;max-height:118px;margin:0 auto 8px;object-fit:contain}
.stp .s1{font-family:var(--f-d);font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
.stp .s2{font-family:var(--f-d);font-size:16px;font-weight:700;margin:3px 0 5px}
/* اسم من اعتمد الختم ووقته: يبقى سجلًّا صغيرًا تحت الختم — لا داخل إطار */
.stp .s3{font-size:10.5px;line-height:1.7;color:var(--dim)}
.stp .s3 b{font-weight:700;color:var(--mut)}
/* الحالة الفارغة وحدها تُبقي إطارًا خفيفًا — لا ختم يُعرض، فيلزم موضع يدلّ
   على مكانه. وهو **ليس** إطارًا حول ختم، بل بديل مؤقت عنه. */
.stp.empty{border:1px dashed color-mix(in srgb,var(--dim) 40%,transparent);border-radius:9px;padding:12px 14px;background:rgba(255,255,255,.02)}
.stp.empty .s1,.stp.empty .s2,.stp.empty .s3{color:var(--dim)}
.stp.empty .s2{font-size:13px}

.foot{padding:16px 26px 20px;border-top:1px solid var(--line);font-size:11px;color:var(--dim);line-height:2}
.foot b{color:var(--mut)}

@media print{
  @page{size:A4;margin:12mm}
  body{background:#fff;color:#14110C;padding:0}
  .toolbar{display:none}
  .inv{max-width:none;border:0;border-radius:0;background:#fff;color:#14110C}
  .inv-hd{border-bottom-color:#14110C}
  .store b,.doc .code,.meta b,.tot b,.tot .grand span,table.items td:first-child,table.items td.num{color:#14110C}
  .store span,.doc .date,.meta h4,.meta p,.foot,.tot div,.tot .fx,.stamps h4{color:#5C564C}
  .doc .t.cert{background:#14110C;color:#fff}
  .doc .t.draft{background:#F2EEE6;color:#5C564C;border-color:#B4B2A9}
  table.items th{background:#F7F4ED;color:#5C564C;border-bottom-color:#14110C}
  table.items td{border-bottom-color:#E2DCD0;color:#5C564C}
  .tot{border-top-color:#E2DCD0}
  .tot .grand{border-top-color:#14110C}
  .tot .grand b{color:#8A6522}
  .stamps{background:#FAF8F4;border-top-color:#E2DCD0}
  /* لا ألوان للختم المرفوع: يُطبع كما هو بلا إطار ولا خلفية مطبوعة */
  .stp.empty{border-color:#C4BDB0}
  .stp.empty .s1,.stp.empty .s2,.stp.empty .s3{color:#8A8375}
  .foot{border-top-color:#E2DCD0}
}
</style>
</head>
<body>

@php
    $certified = $order->stamped_at !== null;
    $paid = $order->payment_confirmed_at !== null;
    $rate = (float) $order->exchange_rate;
@endphp

<div class="toolbar">
    <button class="btn brass" onclick="window.print()">طباعة / PDF</button>
    @if ($waLink)
        <a class="btn wa" href="{{ $waLink }}" target="_blank" rel="noopener">إرسال واتساب</a>
    @endif
    <a class="btn" href="{{ url('/admin/orders/'.$order->id) }}">رجوع للطلب</a>
</div>

<div class="inv">
    <div class="inv-hd">
        <div class="logo">ن</div>
        <div class="store">
            <b>{{ setting('store_name_ar', 'نداف') }}</b>
            <span>{{ setting('invoice_subtitle', 'كرافات وأطقم رسمية وإكسسوارات') }}</span>
            @if (setting('store_phone'))
                <span>{{ setting('store_phone') }}</span>
            @endif
        </div>
        <div class="doc">
            <span class="t {{ $certified ? 'cert' : 'draft' }}">
                {{ $certified ? setting('invoice_title_certified', 'فاتورة نظامية معتمدة') : setting('invoice_title_draft', 'إيصال طلب') }}
            </span>
            <div class="code">{{ $order->order_code }}</div>
            <div class="date">{{ $order->created_at->format('Y/m/d — H:i') }}</div>
        </div>
    </div>

    <div class="meta">
        <div>
            <h4>بيانات العميل</h4>
            <p>
                <b>{{ $order->customerName() }}</b><br>
                {{ $order->customerPhone() ?? '—' }}<br>
                @if ($order->shipping_method === 'local')
                    {{ $order->city ?: '—' }}@if ($order->shipping_address) — {{ $order->shipping_address }}@endif
                @else
                    استلام من المحل
                @endif
            </p>
        </div>
        <div>
            <h4>تفاصيل الطلب</h4>
            <p>
                طريقة الدفع: <b>{{ $order->paymentMethod?->name ?? '—' }}</b><br>
                الاستلام: <b>{{ $order->shipping_method === 'local' ? 'توصيل محلي' : 'استلام من المحل' }}</b><br>
                سعر الصرف: <b>1 $ = {{ number_format($rate, 0) }} ل.س</b>
                @if ($order->payment_reference)
                    <br>رقم الحوالة: <b>{{ $order->payment_reference }}</b>
                @endif
            </p>
        </div>
    </div>

    <table class="items n">
        <thead>
            <tr>
                <th>الوصف</th><th>اللون</th><th>المقاس</th>
                <th style="text-align:end">الكمية</th>
                <th style="text-align:end">السعر</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->displayName() }}</td>
                    <td>{{ $item->color ?: '—' }}</td>
                    <td>{{ $item->size ?: '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price_usd * $rate, 0) }}</td>
                    <td class="num">{{ number_format((float) $item->total_price_usd * $rate, 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="tot n">
        <div><span>المجموع الفرعي</span><b>{{ number_format((float) $order->subtotal_usd * $rate, 0) }} ل.س</b></div>
        @if ((float) $order->discount_usd > 0)
            <div><span>الخصم</span><b>− {{ number_format((float) $order->discount_usd * $rate, 0) }} ل.س</b></div>
        @endif
        @if ((float) $order->shipping_usd > 0)
            <div><span>الشحن</span><b>{{ number_format((float) $order->shipping_usd * $rate, 0) }} ل.س</b></div>
        @endif
        <div class="grand">
            <span>{{ $paid ? 'الإجمالي المقبوض' : 'الإجمالي المستحق' }}</span>
            <b>{{ fmt_syp($order->total_syp) }}</b>
        </div>
        <div class="fx">ما يعادل بالدولار: {{ fmt_usd($order->total_usd) }} — سعر الصرف مثبّت لحظة الطلب</div>
    </div>

    {{-- الأختام: تُثبَّت بفعل صريح من الأدمن، واسمه ووقته يُسجَّلان تلقائيًا --}}
    <div class="stamps">
        <h4>الأختام — تُثبَّت من إدارة المتجر</h4>
        <div class="srow">
            @if ($paid)
                <div class="stp green">
                    {{-- الختم المرفوع من الإعدادات يتقدّم على المرسوم --}}
                    @if ($stampGreenUrl)
                        <img src="{{ $stampGreenUrl }}" alt="ختم قبض الدفع" class="stp-img">
                    @else
                        <div class="s1">✓ الختم الأخضر</div>
                        <div class="s2">تم قبض الدفع</div>
                    @endif
                    <div class="s3">
                        <b>{{ $order->paymentConfirmedBy?->name ?? '—' }}</b><br>
                        {{ $order->payment_confirmed_at->format('Y/m/d · H:i') }}
                    </div>
                </div>
            @else
                <div class="stp empty">
                    <div class="s1">الختم الأخضر</div>
                    <div class="s2">لم يُقبض بعد</div>
                    <div class="s3">يُثبَّت عند تأكيد قبض المال</div>
                </div>
            @endif

            @if ($certified)
                <div class="stp gold">
                    {{-- الختم المرفوع من الإعدادات يتقدّم على المرسوم --}}
                    @if ($stampGoldUrl)
                        <img src="{{ $stampGoldUrl }}" alt="ختم اعتماد الفاتورة" class="stp-img">
                    @else
                        <div class="s1">◆ الختم الذهبي</div>
                        <div class="s2">تم تسليم الطلب</div>
                    @endif
                    <div class="s3">
                        <b>{{ $order->stampedBy?->name ?? '—' }}</b><br>
                        {{ $order->stamped_at->format('Y/m/d · H:i') }}
                    </div>
                </div>
            @else
                <div class="stp empty">
                    <div class="s1">الختم الذهبي</div>
                    <div class="s2">لم يُسلَّم بعد</div>
                    <div class="s3">يُثبَّت عند تسليم الطلب</div>
                </div>
            @endif
        </div>
    </div>

    <div class="foot">
        @if ($certified)
            <b>فاتورة معتمدة.</b> تحمل ختم قبض الدفع وختم التسليم مع اسم من اعتمدها وتاريخها.
        @else
            <b>إيصال غير معتمد.</b> يتحوّل إلى فاتورة نظامية بعد ختمه من إدارة المتجر.
        @endif
        <br>{{ setting('invoice_footer_note', 'الاستبدال خلال ٣ أيام من الاستلام بشرط سلامة القطعة.') }}
    </div>
</div>

</body>
</html>
