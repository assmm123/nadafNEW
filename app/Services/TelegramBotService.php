<?php

namespace App\Services;

use App\Models\BotPendingEdit;
use App\Models\BotSession;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * البوت التفاعلي — يستقبل أوامر الأدمن عبر Telegram (استقصاء getUpdates).
 * التوكن من لوحة الإعدادات: telegram_command_bot_token، والصلاحية عبر telegram_admin_chat_id.
 */
class TelegramBotService
{
    public static function token(): ?string
    {
        return Setting::get('telegram_command_bot_token');
    }

    /** جلب رسائل جديدة بالاستقصاء */
    public static function fetchUpdates(): array
    {
        $token = self::token();
        if (! $token) {
            return [];
        }

        $offset = (int) Setting::get('bot_update_offset', 0);
        $client = Http::timeout(35);

        if (! Setting::bool('telegram_verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        try {
            $response = $client->get("https://api.telegram.org/bot{$token}/getUpdates", [
                'offset' => $offset + 1,
                'timeout' => 30,
                'allowed_updates' => json_encode(['message']),
            ])->json();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $updates = $response['result'] ?? [];

        if (! empty($updates)) {
            Setting::set('bot_update_offset', (string) end($updates)['update_id']);
        }

        return $updates;
    }

    /** معالجة كل التحديثات المعلقة */
    public static function poll(): int
    {
        $updates = self::fetchUpdates();
        $allowed = Setting::get('telegram_admin_chat_id');

        foreach ($updates as $update) {
            $msg = $update['message'] ?? null;
            if (! $msg || ! isset($msg['text'])) {
                continue;
            }

            $chatId = (string) $msg['chat']['id'];

            // حماية: الأوامر لصاحب المتجر فقط
            // الرفض إن لم يُضبط معرّف الأدمن في الإعدادات أو اختلف عن مُرسل الرسالة —
            // لا نسمح بتجاوز الحارس عندما يكون الإعداد فارغًا.
            if (! $allowed || (string) $allowed !== $chatId) {
                self::reply($chatId, "🔒 هذا البوت خاص بإدارة متجر نداف.\nchat_id الخاص بك: {$chatId} — إن كنت المدير أضفه في الإعدادات.");

                continue;
            }

            $reply = self::handle(
                $chatId,
                trim($msg['text']),
                $msg['chat']['username'] ?? null,
                isset($update['update_id']) ? (int) $update['update_id'] : null,
            );
            self::reply($chatId, $reply);
        }

        return count($updates);
    }

    private static function reply(int|string $chatId, string $text): void
    {
        $token = self::token();
        if (! $token) {
            return;
        }

        $client = Http::timeout(12);
        if (! Setting::bool('telegram_verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        try {
            $client->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => mb_substr($text, 0, 4000),
            ])->throw();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** التوجيه الرئيسي للأوامر */
    public static function handle(string $chatId, string $text, ?string $username = null, ?int $updateId = null): string
    {
        // سجل الرسالة.
        // عمود update_id فريد، وكان السابق يولّد المعرّف من microtime بالمللي ثانية —
        // فمعالجة عدة رسائل في نفس المللي ثانية (شائع عند وصول تحديثات متراكمة)
        // تُصطدم بالقيود الفريدة وترمي استثناءً يوقف معالجة بقية الرسائل.
        // الحل: استخدام معرّف التحديث الحقيقي من تيليجرام، مع تجاهل أي تكرار بأمان.
        $logId = $updateId ?? (int) round(microtime(true) * 1000);

        if (! \App\Models\BotMessageLog::where('update_id', $logId)->exists()) {
            \App\Models\BotMessageLog::create([
                'update_id' => $logId,
                'chat_id' => (int) $chatId,
                'username' => $username,
                'text' => $text,
                'reply' => null,
            ]);
        }

        BotSession::touchSession((int) $chatId);

        // إذا في انتظار قيمة تعديل → التقطها
        $session = BotSession::current((int) $chatId);
        if ($session->state === 'awaiting_edit_value' && isset($session->context['pending_edit_id'])) {
            return self::applyPendingEdit((int) $chatId, $text);
        }

        if (str_starts_with($text, '/')) {
            return self::command($chatId, $text);
        }

        // أوامر عربية مختصرة — match يستخدم === صارمًا لذا نحوّل preg_match إلى bool
        return match (true) {
            (bool) preg_match('/^(كمية|qty)\s+(-?\d+)\s+(.+)$/u', $text, $m) => self::handleEditCommand($chatId, 'quantity', $m[2], trim($m[3])),
            (bool) preg_match('/^(سعر|price)\s+([\d.]+)\s+(.+)$/u', $text, $m2) => self::handleEditCommand($chatId, 'price', $m2[2], trim($m2[3])),
            str_contains($text, 'منخفض') => self::cmdLow(),
            str_contains($text, 'نفد') => self::cmdOut(),
            str_contains($text, 'طلبات') => self::cmdRecentOrders(),
            str_contains($text, 'مبيعات') || str_contains($text, 'تقرير') => self::cmdSales($chatId, str_contains($text, 'أسبوع') || str_contains($text, 'اسبوع') ? 'this_week' : (str_contains($text, 'شهر') ? 'this_month' : 'today')),
            str_contains($text, 'جرد') || str_contains($text, 'مخزون') => self::cmdStock($chatId, (int) (preg_replace('/\D/', '', $text) ?: 1) - 1),
            str_contains($text, 'مساعدة') || $text === 'بدء' || $text === 'start' => self::cmdHelp(),
            default => self::searchByCode($text),
        };
    }

    private static function command(string $chatId, string $text): string
    {
        [$cmd, $arg] = array_pad(explode(' ', $text, 2), 2, null);

        return match ($cmd) {
            '/start', '/help' => self::cmdHelp(),
            '/stock' => self::cmdStock($chatId, (int) ($arg ?? 0)),
            '/low' => self::cmdLow(),
            '/out' => self::cmdOut(),
            '/sales' => self::cmdSales($chatId, $arg ?? 'today'),
            '/find', '/بحث' => self::searchByCode($arg ?? ''),
            '/orders' => self::cmdRecentOrders(),
            default => self::cmdHelp(),
        };
    }

    private static function cmdHelp(): string
    {
        return "🤖 بوت نداف التفاعلي — الأوامر:\n\n".
            "📦 «جرد» — قائمة المخزون (جرد 2 للصفحة الثانية)\n".
            "⚠️ «منخفض» — الأصناف قاربت النفاد\n".
            "🚨 «نفد» — الأصناف التي رصيدها 0\n".
            "💰 «مبيعات» — مبيعات اليوم (تقرير أسبوع/شهر)\n".
            "📋 «طلبات» — آخر 5 طلبات\n".
            "🔍 اكتب كود منتج (داخلي أو اسم) للبحث\n".
            "✏️ «سعر 14500 [كود]» أو «كمية 25 [كود]» ثم أكّد\n\n".
            "أمثلة:\n• منخفض\n• كمية 25 CRV-01\n• سعر 12.5 CRV-01";
    }

    /** قائمة المخزون مع تقسيم صفحات (15 لكل صفحة) */
    private static function cmdStock(string $chatId, int $page): string
    {
        $perPage = 15;
        $variants = ProductVariant::with('product')->orderBy('quantity')->get();
        $total = $variants->count();

        if ($total === 0) {
            return "المخزون فارغ.";
        }

        $page = max(0, min($page, intdiv($total - 1, $perPage)));
        $slice = $variants->slice($page * $perPage, $perPage);

        $lines = ["📦 المخزون (صفحة ".($page + 1).'/'.(intdiv($total - 1, $perPage) + 1).") — إجمالي {$total} صنف:\n"];
        foreach ($slice as $v) {
            $icon = $v->quantity <= 0 ? '🔴' : ($v->quantity <= $v->low_stock_threshold ? '🟡' : '🟢');
            $label = $v->label() ? " ({$v->label()})" : '';
            $code = $v->product->internal_code ? " [{$v->product->internal_code}]" : '';
            $lines[] = "{$icon} {$v->product->name_ar}{$label}{$code}: {$v->quantity}";
        }
        $lines[] = "\nاستخدم «جرد ".($page + 2)."» للصفحة التالية إن وُجدت.";

        return implode("\n", $lines);
    }

    private static function cmdLow(): string
    {
        $low = ProductVariant::with('product')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->where('quantity', '>', 0)
            ->orderBy('quantity')
            ->get();

        if ($low->isEmpty()) {
            return "✅ لا أصناف منخفضة — كل المخزون فوق الحد الآمن.";
        }

        $lines = ["⚠️ أصناف منخفضة ({$low->count()}):\n"];
        foreach ($low as $v) {
            $label = $v->label() ? " ({$v->label()})" : '';
            $code = $v->product->internal_code ? " [{$v->product->internal_code}]" : '';
            $lines[] = "🟡 {$v->product->name_ar}{$label}{$code}: بقي {$v->quantity} (حد: {$v->low_stock_threshold})";
        }
        $lines[] = "\n💡 اقتراح: أنشئ فاتورة شراء لهذه الأصناف قريبًا.";

        return implode("\n", $lines);
    }

    private static function cmdOut(): string
    {
        $out = ProductVariant::with('product')->where('quantity', '<=', 0)->get();

        if ($out->isEmpty()) {
            return "✅ لا أصناف نافدة.";
        }

        $lines = ["🚨 أصناف نافدة ({$out->count()}):\n"];
        foreach ($out as $v) {
            $label = $v->label() ? " ({$v->label()})" : '';
            $code = $v->product->internal_code ? " [{$v->product->internal_code}]" : '';
            $lines[] = "🔴 {$v->product->name_ar}{$label}{$code}: 0";
        }

        return implode("\n", $lines);
    }

    private static function cmdSales(string $chatId, string $range): string
    {
        [$from, $to, $label] = ReportService::resolveRange(
            in_array($range, ['today', 'week', 'month', 'أسبوع', 'شهر']) ? $range : 'today'
        );

        $summary = ReportService::summary($from, $to);

        return "💰 مبيعات {$label}:\n\n".
            "• الطلبات: {$summary['ordersCount']}\n".
            '• المبيعات: '.fmt_usd($summary['salesUsd'])."\n".
            '• بالليرة: '.number_format($summary['salesSyp'])." ل.س\n".
            '• الأرباح المقدّرة: '.fmt_usd($summary['profitUsd'])."\n".
            '• عناصر مبيعة: '.$summary['itemsSold'];
    }

    private static function cmdRecentOrders(): string
    {
        $orders = Order::with('user')->latest()->take(5)->get();

        if ($orders->isEmpty()) {
            return "لا طلبات بعد.";
        }

        $lines = ["📋 آخر 5 طلبات:\n"];
        foreach ($orders as $o) {
            $lines[] = "• {$o->order_code} — {$o->user->name} — ".fmt_usd($o->total_usd).' — '.Order::statusLabel($o->status);
        }

        return implode("\n", $lines);
    }

    /** البحث بالكود الداخلي أو جزء من الاسم */
    private static function searchByCode(string $query): string
    {
        if (mb_strlen(trim($query)) < 2) {
            return "لم أفهم الطلب — اكتب «مساعدة» لعرض الأوامر، أو كود/اسم منتج للبحث.";
        }

        $variants = ProductVariant::with('product')
            ->where(function ($q) use ($query) {
                $q->whereHas('product', fn ($p) => $p
                    ->where('internal_code', 'like', "%{$query}%")
                    ->orWhere('name_ar', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%"));
            })
            ->limit(10)
            ->get();

        if ($variants->isEmpty()) {
            return "🔍 لا نتائج لـ «{$query}».";
        }

        $lines = ["🔍 نتائج البحث عن «{$query}»:\n"];
        foreach ($variants as $v) {
            $label = $v->label() ? " ({$v->label()})" : '';
            $code = $v->product->internal_code ? "\n   كود: {$v->product->internal_code}" : '';
            // السعر عمود على المنتج — لا يوجد price_usd في جدول product_variants
            $price = $v->product->price_usd;
            $lines[] = "📦 {$v->product->name_ar}{$label}\n   المخزون: {$v->quantity} — السعر: ".fmt_usd((float) $price).$code;
        }
        $lines[] = "\n✏️ للتعديل: «كمية 25 [الكود]» أو «سعر 12.5 [الكود]»";

        return implode("\n", $lines);
    }

    /** بدء تعديل: «كمية 25 CRV-01» → ينشئ مسودة بانتظار التأكيد */
    public static function handleEditCommand(string $chatId, string $field, string $value, string $code): string
    {
        $variant = self::findVariantByCode($code);
        if (! $variant) {
            return "🔍 لا منتج بكود «{$code}» — ابحث بالاسم أو اكتب الكود الداخلي كاملًا.";
        }

        $pending = BotPendingEdit::create([
            'variant_id' => $variant->id,
            'field' => $field,
            'new_value' => $value,
            'chat_id' => (int) $chatId,
            'expires_at' => now()->addMinutes(10),
        ]);

        BotSession::setState((int) $chatId, 'awaiting_edit_value', ['pending_edit_id' => $pending->id]);

        $label = $variant->label() ? " ({$variant->label()})" : '';
        $fieldAr = $field === 'price' ? 'السعر' : 'الكمية';

        // السعر على مستوى المنتج لا المتغير: تعديله يسري على كل متغيرات المنتج
        $current = $field === 'price'
            ? fmt_usd((float) $variant->product->price_usd)
            : $variant->quantity;

        $new = $field === 'price' ? fmt_usd((float) $value) : $value;

        $priceNote = $field === 'price'
            ? "\n⚠️ السعر خاصية للمنتج — سيُطبَّق على كل متغيراته."
            : '';

        return "✏️ مسودة تعديل:\n\n".
            "📦 {$variant->product->name_ar}{$label}\n".
            "الحالي: {$fieldAr} = {$current}\n".
            "الجديد: {$fieldAr} = {$new}{$priceNote}\n\n".
            "أرسل «تأكيد» لتطبيق التعديل أو «إلغاء» للتراجع.\n⏱ تنتهي صلاحية المسودة بعد 10 دقائق.";
    }

    /** تطبيق/إلغاء المسودة عند رد الأدمن */
    private static function applyPendingEdit(int $chatId, string $text): string
    {
        $session = BotSession::current($chatId);
        $pending = BotPendingEdit::valid()->find($session->context['pending_edit_id']);

        if (str_contains($text, 'إلغاء') || str_contains($text, 'الغاء') || str_contains($text, 'cancel')) {
            $pending?->delete();
            BotSession::setState($chatId, 'idle');

            return "✅ أُلغي التعديل.";
        }

        if (! str_contains($text, 'تأكيد') && ! str_contains($text, 'تاكيد') && ! str_contains($text, 'نعم')) {
            return "لم أفهم — أرسل «تأكيد» للتطبيق أو «إلغاء» للتراجع.";
        }

        if (! $pending) {
            BotSession::setState($chatId, 'idle');

            return "⏱ انتهت صلاحية المسودة — ابدأ التعديل من جديد.";
        }

        $variant = $pending->variant;
        $oldValue = $pending->field === 'price'
            ? $variant->product->price_usd
            : $variant->quantity;

        if ($pending->field === 'price') {
            if (! is_numeric($pending->new_value) || (float) $pending->new_value < 0) {
                BotSession::setState($chatId, 'idle');

                return "❌ قيمة السعر غير صالحة — ابدأ التعديل من جديد.";
            }

            // السعر عمود على المنتج لا على المتغير. الكتابة السابقة
            // ($variant->price_usd = ...) كانت تُفشل الحفظ بخطأ SQL:
            // العمود price_usd غير موجود في جدول product_variants.
            $variant->product->price_usd = round((float) $pending->new_value, 2);
            $variant->product->save();
        } else {
            if (! is_numeric($pending->new_value) || (int) $pending->new_value < 0) {
                BotSession::setState($chatId, 'idle');

                return "❌ قيمة الكمية غير صالحة — ابدأ التعديل من جديد.";
            }
            // تعديل الكمية عبر StockService ليُوثق كحركة تعديل
            \App\Services\StockService::record(
                $variant->id,
                'adjust',
                (int) $pending->new_value - $variant->quantity,
                null,
                null,
                'تعديل من بوت تيليجرام'
            );
            $variant->refresh();
        }

        // في فرع السعر حُفظ المنتج أعلاه، وفي فرع الكمية حُفظ المتغير عبر StockService.
        // هذا الحفظ يبقى للاحتياط (لا يُنفَّذ إن لم يوجد تغيير فعلي).
        $variant->save();
        $pending->delete();
        BotSession::setState($chatId, 'idle');

        $fieldAr = $pending->field === 'price' ? 'السعر' : 'الكمية';

        $after = $pending->field === 'price'
            ? fmt_usd((float) $variant->product->price_usd)
            : $variant->quantity;

        $scope = $pending->field === 'price' ? "\n⚠️ سُعّر المنتج كاملًا (كل متغيراته)" : '';

        return "✅ طُبّق التعديل:\n".
            "📦 {$variant->product->name_ar}\n".
            "{$fieldAr}: {$oldValue} ← {$after}{$scope}";
    }

    /** إيجاد متغير بكود المنتج الداخلي أو اسم المنتج */
    private static function findVariantByCode(string $code): ?ProductVariant
    {
        return ProductVariant::with('product')
            ->whereHas('product', fn ($p) => $p
                ->where('internal_code', $code)
                ->orWhere('name_ar', 'like', "%{$code}%")
                ->orWhere('name_en', 'like', "%{$code}%"))
            ->first();
    }
}
