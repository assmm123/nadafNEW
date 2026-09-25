<?php

namespace App\Http\Controllers;

use App\Models\Order;

class InvoiceController extends Controller
{
    public function show(Order $order)
    {
        // الفاتورة تعرض بيانات الدفع والعميل — تشترط صلاحية عرض الطلبات.
        // (مسار /admin غير تابع لـ Filament فلا تمر عليه السياسات تلقائيًا.)
        abort_unless(auth()->user()?->hasPermission('orders.view'), 403);

        $order->load(['items', 'user', 'paymentMethod']);

        return view('admin.invoice', [
            'order' => $order,
            'waLink' => self::whatsappLink($order),
            // الأختام التي رفعها المالك من «الإعدادات ← الهوية البصرية».
            // وحقل الإعدادات يَعِد صراحةً بأنها «تُستخدم على الفاتورة بدل الختم
            // المرسوم» — والوعد كان غير منفَّذ، فالفاتورة كانت ترسم ختمًا نصيًّا
            // دائمًا ولا تعرض الصورة المرفوعة إطلاقًا.
            'stampGreenUrl' => self::stampUrl('stamp_green_path'),
            'stampGoldUrl' => self::stampUrl('stamp_gold_path'),
        ]);
    }

    /** رابط صورة ختم من الإعدادات — null إن لم يُرفع شيء */
    private static function stampUrl(string $key): ?string
    {
        $path = setting($key);

        return filled($path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($path)
            : null;
    }

    /**
     * نص الفاتورة المنسق للواتساب + رابط جاهز للإرسال.
     *
     * الرسالة تُرسل **للعميل** (رقم صاحب الطلب)، فمحتواها ما يحتاجه العميل:
     * بياناته، وعنوان التوصيل، والأصناف بمواصفاتها وسعر الوحدة، والحساب
     * كاملًا، وطريقة الدفع وحالتها. وكانت قبل ذلك مختصرة: كود وتاريخ واسم
     * وأصناف بإجماليها فقط — بلا هاتف ولا عنوان ولا سعر وحدة ولا خصم مفصّل.
     *
     * ولا يُدرج رابط الفاتورة الإدارية: مسارها محمي بصلاحية `orders.view`
     * فلن يفتحه العميل، وإدراجه يسرّب مسارًا داخليًّا بلا فائدة.
     */
    public static function whatsappLink(Order $order): string
    {
        $storeName = setting('store_name_ar', 'نداف');
        $line = '━━━━━━━━━━━━━';

        $lines = [
            "🧾 *فاتورة طلب — {$storeName}*",
            $line,
            "كود الطلب: *{$order->order_code}*",
            'التاريخ: '.$order->created_at->format('Y/m/d H:i'),
            'الحالة: '.Order::statusLabel($order->status),
        ];

        // ── العميل ──
        $lines[] = $line;
        $lines[] = '*بيانات العميل*';
        $lines[] = 'الاسم: '.$order->customerName();

        if (filled($order->customerPhone())) {
            $lines[] = 'الهاتف: '.$order->customerPhone();
        }
        if (filled($order->customerEmail())) {
            $lines[] = 'البريد: '.$order->customerEmail();
        }

        // ── التوصيل ──
        $lines[] = $line;
        $lines[] = '*التوصيل*';
        $lines[] = 'الطريقة: '.self::shippingLabel($order->shipping_method);

        if (filled($order->city)) {
            $lines[] = 'المدينة: '.$order->city;
        }
        if (filled($order->shipping_address)) {
            $lines[] = 'العنوان: '.$order->shipping_address;
        }

        // ── الأصناف: اسم + مواصفة + كمية × سعر الوحدة = الإجمالي ──
        $lines[] = $line;
        $lines[] = '*الأصناف*';

        $i = 1;
        foreach ($order->items as $item) {
            $spec = trim(($item->color ?? '').' / '.($item->size ?? ''), ' /');

            $lines[] = "{$i}) {$item->name_ar}".($spec !== '' ? " — {$spec}" : '');
            $lines[] = '   '.$item->quantity.' × '.fmt_usd($item->unit_price_usd).' = '
                .fmt_usd($item->total_price_usd).($item->is_wholesale ? ' (جملة)' : '');
            $i++;
        }

        // ── الحساب ──
        $lines[] = $line;
        $lines[] = 'المجموع الفرعي: '.fmt_usd($order->subtotal_usd);

        if ($order->discount_usd > 0) {
            $lines[] = 'الخصم'.($order->coupon ? " ({$order->coupon->code})" : '').': −'.fmt_usd($order->discount_usd);
        }
        if ($order->shipping_usd > 0) {
            $lines[] = 'الشحن: '.fmt_usd($order->shipping_usd);
        }

        $lines[] = '*الإجمالي: '.fmt_usd($order->total_usd).' / '.fmt_syp($order->total_syp).'*';

        if ($order->exchange_rate) {
            $lines[] = 'سعر الصرف: '.number_format((float) $order->exchange_rate).' ل.س للدولار';
        }

        // ── الدفع وحالته ──
        $lines[] = $line;
        $lines[] = '*الدفع*';
        $lines[] = 'الطريقة: '.($order->paymentMethod?->name ?? '—');

        if (filled($order->payment_reference)) {
            $lines[] = 'رقم الحوالة: '.$order->payment_reference;
        }
        if (filled($order->payment_sender_name)) {
            $lines[] = 'اسم المُحوِّل: '.$order->payment_sender_name;
        }

        if ($order->payment_confirmed_at) {
            $lines[] = 'تم قبض الدفع: '.$order->payment_confirmed_at->format('Y/m/d H:i');
        } elseif ($order->paymentMethod && ! $order->paymentMethod->requires_proof) {
            $lines[] = 'الدفع عند التسليم';
        } else {
            $lines[] = 'إثبات الدفع: '.($order->payment_proof_path ? 'مرفق ✓' : 'بانتظار المراجعة');
        }

        if (filled($order->notes)) {
            $lines[] = $line;
            $lines[] = 'ملاحظات الطلب: '.$order->notes;
        }

        $lines[] = $line;
        $lines[] = "شكرًا لتسوقكم من متجر {$storeName} 💛";

        // `wa_digits` تُزيل بادئة `00` — ورقم مكتوب `00963...` كان يُنتج رابطًا لا يفتح
        $phone = wa_digits($order->customerPhone() ?? '');

        return $phone
            ? "https://wa.me/{$phone}?text=".rawurlencode(implode("\n", $lines))
            : 'https://wa.me/?text='.rawurlencode(implode("\n", $lines));
    }

    /** تسمية طريقة التوصيل كما تظهر للعميل */
    private static function shippingLabel(?string $method): string
    {
        return match ($method) {
            'pickup' => 'استلام من المتجر',
            'local' => 'توصيل محلي',
            default => filled($method) ? $method : '—',
        };
    }
}
