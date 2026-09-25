<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramService
{
    /**
     * تهريب نصوص HTML قبل إرسالها مع parse_mode=HTML.
     *
     * بدونها يرفض تيليجرام الرسالة بخطأ 400 (can't parse entities) إذا احتوى
     * اسم عميل أو ملاحظة أو رقم حوالة أو عنوان على < أو > أو & — فيضيع
     * الإشعار بصمت لأن النداء داخل try/catch في المستدعي.
     */
    private static function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** عميل HTTP مع خيار تجاهل تحقق SSL (للبيئات خلف VPN/مضاد فيروسات يعترض الشهادات) */
    private static function client()
    {
        $client = Http::timeout(12);

        if (! Setting::bool('telegram_verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /** تحديد المحادثة الهدف: orders | inventory | main */
    private static function chat(string $kind): ?string
    {
        if (! self::token($kind)) {
            return null;
        }

        return match ($kind) {
            'orders' => Setting::get('telegram_orders_chat_id') ?: Setting::get('telegram_chat_id'),
            'inventory' => Setting::get('telegram_inventory_chat_id') ?: Setting::get('telegram_chat_id'),
            default => Setting::get('telegram_chat_id'),
        };
    }

    /**
     * توكن البوت بحسب الغرض.
     *
     * اللوحة تعرض **ثلاثة بوتات** — طلبات · مخزون · تفاعلي — ووصفها يَعِد بأن
     * لكل غرض توكنه. وكانت الحقول تُحفظ فعلًا و**لا يقرأها شيء**: كل الرسائل
     * تخرج بالتوكن الأساسي. فصار لكل غرض توكنه، وإن تُرك فارغًا عاد إلى
     * الأساسي كما ينصّ تلميح الحقل نفسه — فالسلوك لا يتغيّر لمن تركها فارغة.
     */
    private static function token(string $kind = 'main'): string
    {
        $fallback = (string) Setting::get('telegram_bot_token');

        return (string) match ($kind) {
            'orders' => Setting::get('bot_orders_token') ?: $fallback,
            'inventory' => Setting::get('bot_inventory_token') ?: $fallback,
            default => $fallback,
        };
    }

    private static function send(string $chatId, string $text, string $kind = 'main'): void
    {
        $token = self::token($kind);

        if (! $token) {
            return;
        }

        self::client()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ])->throw();
    }

    /** إرسال صورة مع تعليق (لإثبات الدفع) */
    private static function sendPhoto(string $chatId, string $diskPath, string $caption, string $kind = 'main'): void
    {
        $token = self::token($kind);
        $fullPath = Storage::disk('public')->path($diskPath);

        if (! $token || ! is_file($fullPath)) {
            self::send($chatId, $caption."\n📎 (تعذر إرفاق صورة الإثبات)", $kind);

            return;
        }

        self::client()
            ->attach('photo', file_get_contents($fullPath), basename($diskPath))
            ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                'chat_id' => $chatId,
                'caption' => mb_substr($caption, 0, 1000),
                'parse_mode' => 'HTML',
            ])->throw();
    }

    /** إشعار طلب جديد → رسالة منسقة كاملة التفاصيل */
    public static function sendOrder(Order $order): void
    {
        $chatId = self::chat('orders');
        if (! $chatId) {
            return;
        }

        $text = self::orderText($order);

        if ($order->payment_proof_path) {
            self::sendPhoto($chatId, $order->payment_proof_path, $text, 'orders');
        } else {
            self::send($chatId, $text, 'orders');
        }
    }

    /** تحديث حالة طلب → رسالة منسقة بالسياق الكامل */
    public static function sendOrderStatus(Order $order): void
    {
        $chatId = self::chat('orders');
        if (! $chatId) {
            return;
        }

        // بلا 'confirmed' — حُذفت الحالة من Order::STATUSES
        $icons = ['pending' => '📋', 'preparing' => '🧵',
            'shipped' => '🚚', 'delivered' => '🎉', 'cancelled' => '❌'];
        $icon = $icons[$order->status] ?? '📋';
        $st = Order::STATUSES[$order->status] ?? [];

        $lines = [
            "<b>{$icon} تحديث حالة الطلب</b>",
            '<b>الكود:</b> '.$order->order_code,
            '<b>الحالة:</b> '.Order::statusLabel($order->status).' ('.($st['en'] ?? '').')',
            '<b>العميل:</b> '.self::esc($order->customerName()).' — <code>'.preg_replace('/\D/', '', $order->customerPhone() ?? '').'</code>',
            '',
            '<b>🧾 محتوى الطلب:</b>',
        ];
        foreach ($order->items as $item) {
            $lines[] = '• '.self::esc($item->displayName()).' × '.$item->quantity.' = '.fmt_usd($item->total_price_usd);
        }
        $lines[] = '';
        $lines[] = '<b>💰 الإجمالي:</b> '.fmt_usd($order->total_usd).' / '.fmt_syp($order->total_syp);
        // الختم الذهبي صار يعني «تم التسليم» لا «التوثيق» — تغيّر المعنى فوجب تغيّر النص
        $lines[] = '<b>🟢 قبض الدفع:</b> '.($order->payment_confirmed_at
            ? '✓ مُقبوض '.$order->payment_confirmed_at->format('m/d H:i')
            : '⏳ غير مُقبض');
        $lines[] = '<b>◆ التسليم:</b> '.($order->stamped_at
            ? '✓ سُلِّم '.$order->stamped_at->format('m/d H:i')
            : '— لم يُسلَّم');

        self::send($chatId, implode("\n", $lines), 'orders');
    }

    /** تنبيه مخزون → قناة الجرد */
    public static function sendInventory(string $text): void
    {
        $chatId = self::chat('inventory');
        if (! $chatId) {
            return;
        }

        self::send($chatId, $text, 'inventory');
    }

    /** تنبيه انخفاض/نفاد مخزون متغير — رسالة كاملة بسياق البيانات والمبيعات */
    public static function lowStockAlert($variant): void
    {
        $name = self::esc($variant->product?->name_ar);
        $opts = $variant->label() ? ' ('.self::esc($variant->label()).')' : '';
        $sku = self::esc($variant->sku ?: ($variant->product?->internal_code ? '[SKU: '.$variant->product->internal_code.']' : ''));
        $out = $variant->quantity <= 0;

        // مبيعات هذا المتغير آخر 7 أيام (لترشيد قرار الشراء)
        $sold7 = \App\Models\OrderItem::where('variant_id', $variant->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('quantity');
        $sold30 = \App\Models\OrderItem::where('variant_id', $variant->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('quantity');
        $suggest = max(10, (int) ceil($sold30 * 1.5 / 5) * 5); // تغطية شهر ونصف مقربة لخمسة

        $lines = [
            $out ? '<b>🚨 نفاد مخزون!</b>' : '<b>⚠️ مخزون منخفض</b>',
            '<b>المنتج:</b> '.$name.$opts,
            $sku ? '<b>الرمز:</b> <code>'.$sku.'</code>' : '',
            '<b>الرصيد الحالي:</b> '.max(0, $variant->quantity).' قطعة (حد التنبيه: '.$variant->low_stock_threshold.')',
            '',
            '<b>📊 المبيعات:</b> آخر 7 أيام: '.$sold7.' — آخر 30 يومًا: '.$sold30,
            '<b>💡 اقتراح:</b> أنشئ فاتورة شراء بكمية ~'.$suggest.' قطعة لتغطية شهر ونصف.',
            '',
            '📥 من لوحة الأدمن: فواتير الشراء ← فاتورة جديدة لهذا الصنف.',
        ];

        self::sendInventory(implode("\n", array_filter($lines, fn ($l) => $l !== '')));
    }

    public static function test(string $token, string $chatId): array
    {
        $response = self::client()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => '✅ رسالة تجريبية من متجر نداف — إعداد الإشعارات يعمل بنجاح.',
            ]);

        return $response->json() ?? ['ok' => false];
    }

    private static function orderText(Order $order): string
    {
        $date = $order->created_at->format('Y/m/d — H:i');
        $lines = [
            '<b>🛒 طلب جديد — متجر نداف</b>',
            '<b>الكود:</b> <code>'.$order->order_code.'</code>',
            '<b>التاريخ:</b> '.$date,
            '',
            '<b>👤 العميل:</b> '.self::esc($order->customerName()),
            '<b>📞 الهاتف:</b> <code>'.preg_replace('/\D/', '', $order->customerPhone() ?? '—').'</code>',
            '',
            '<b>🧾 العناصر:</b>',
        ];

        $i = 1;
        foreach ($order->items as $item) {
            $sku = $item->variant?->sku ?: '';
            $whole = $item->is_wholesale ? ' <i>(جملة)</i>' : '';
            $skuPart = $sku ? ' <code>'.self::esc($sku).'</code>' : '';
            $lines[] = sprintf('%d) %s%s × %d = <b>%s</b>%s', $i, self::esc($item->displayName()), $skuPart, $item->quantity, fmt_usd($item->total_price_usd), $whole);
            $i++;
        }

        $lines[] = '';
        $lines[] = '<b>💰 الإجماليات:</b>';
        if ($order->discount_usd > 0) {
            $lines[] = '• الخصم: <s>'.fmt_usd($order->discount_usd).'</s>';
        }
        if ($order->shipping_usd > 0) {
            $lines[] = '• الشحن: '.fmt_usd($order->shipping_usd);
        }
        $lines[] = '• <b>الإجمالي: '.fmt_usd($order->total_usd).' / '.fmt_syp($order->total_syp).'</b>';
        $lines[] = '• سعر الصرف المثبت: 1$ = '.number_format($order->exchange_rate, 0).' ل.س';

        $lines[] = '';
        $lines[] = '<b>💳 الدفع:</b> '.self::esc($order->paymentMethod?->name ?? '—');
        if ($order->payment_reference) {
            $lines[] = '<b>🔢 رقم الحوالة:</b> <code>'.self::esc($order->payment_reference).'</code>';
        }
        if ($order->payment_sender_name) {
            $lines[] = '<b>✍️ المرسل:</b> '.self::esc($order->payment_sender_name);
        }
        $lines[] = $order->payment_proof_path
            ? '<b>📎 الإثبات:</b> ✓ مرفق (الصورة أعلاه)'
            : '<b>📎 الإثبات:</b> — لا يوجد';

        $lines[] = '';
        $lines[] = $order->shipping_method === 'local'
            ? '<b>🚚 التوصيل:</b> محلي — '.self::esc($order->city ?: '—')."\n<b>📍 العنوان:</b> ".self::esc($order->shipping_address ?: '—')
            : '<b>🏬 الاستلام:</b> من المحل';
        if ($order->notes) {
            $lines[] = '<b>📝 ملاحظات العميل:</b> '.self::esc($order->notes);
        }

        return implode("\n", $lines);
    }
}
