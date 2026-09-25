<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Notifications\OrderPlaced;
use App\Support\PaymentProof;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * إنشاء الطلب من محتويات السلة + إرسال الإشعارات.
     *
     * فحص التوفّر يحدث **داخل** المعاملة تحت قفل الصفوف، فلا يمكن لطلبين
     * متزامنين أن يبيعا آخر قطعة. أي فشل يُلغي المعاملة بالكامل.
     *
     * @throws ValidationException
     */
    /**
     * @param  \App\Models\User|null  $user  الحساب المسجَّل — أو null لطلب ضيف
     *
     * الطلب بلا حساب صار ممكنًا: يُخزَّن اسم الضيف ورقمه على الطلب نفسه،
     * ولا يُنشأ له حساب. و`$user` تبقى مصدر الاسم والرقم حين يوجد حساب.
     */
    public static function place(?\App\Models\User $user, array $data): Order
    {
        if (CartService::count() === 0) {
            throw ValidationException::withMessages(['cart' => __('cart.empty')]);
        }

        // ── بوابة إثبات الدفع — قبل أي كتابة في القاعدة ──
        // موضعها هنا لا في مكوّن الواجهة: كود الطلب يُولَّد **داخل هذه الخدمة**
        // (السطر `'order_code' => Order::generateCode()` أدناه)، فالمنع يجب أن
        // يقع قبل توليده. وكان الحكم في الواجهة وحدها، فكل مسار لا يمرّ بها
        // كان يُنشئ طلبًا بلا إثبات — وهو أصل البلاغ.
        PaymentProof::assert(
            isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            $data,
        );

        $coupon = null;
        $couponCode = trim($data['coupon_code'] ?? '');
        if ($couponCode !== '') {
            $coupon = Coupon::where('code', $couponCode)->first();
            if (! $coupon || ! $coupon->isValid(CartService::totals()['subtotal_usd'])) {
                throw ValidationException::withMessages(['coupon_code' => __('checkout.invalid_coupon')]);
            }
        }

        $result = DB::transaction(function () use ($user, $data, $coupon) {
            $totals = CartService::totals($data['shipping_method'], $coupon);

            // 0) أعِد التحقق من الكوبون تحت قفل الصف.
            //    الفحص قبل المعاملة لا يكفي: طلبان متزامنان بكوبون حدّه استخدام واحد
            //    يمرّان كلاهما ثم يتجاوز العدّاد الحد المسموح.
            if ($coupon) {
                $lockedCoupon = Coupon::whereKey($coupon->getKey())->lockForUpdate()->first();

                if (! $lockedCoupon || ! $lockedCoupon->isValid($totals['subtotal_usd'])) {
                    throw ValidationException::withMessages(['coupon_code' => __('checkout.invalid_coupon')]);
                }

                $coupon = $lockedCoupon;
            }

            // 1) اقفل صفوف المتغيرات ثم أعد التحقق من التوفّر — ذرّيًا
            $variantIds = $totals['items']
                ->map(fn ($item) => $item->variant?->id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($variantIds) {
                $locked = ProductVariant::whereIn('id', $variantIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $stockErrors = [];
                foreach ($totals['items'] as $item) {
                    if (! $item->variant) {
                        continue;
                    }

                    $available = (int) ($locked->get($item->variant->id)?->quantity ?? 0);
                    if ($available < $item->qty) {
                        $stockErrors[] = __('cart.insufficient_stock', ['name' => $item->product->name]);
                    }
                }

                if ($stockErrors) {
                    throw ValidationException::withMessages(['cart' => implode(' ', $stockErrors)]);
                }
            }

            // 2) أنشئ الطلب مع بيانات الإثبات (تُرفع الملفات قبل الوصول إلى هنا)
            $order = Order::create([
                'user_id' => $user?->id,
                // اسم الضيف ورقمه — يُستخدمان فقط إن لم يكن هناك حساب
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'order_code' => Order::generateCode(),
                'status' => 'pending',
                'subtotal_usd' => $totals['subtotal_usd'],
                'discount_usd' => $totals['discount_usd'],
                'shipping_usd' => $totals['shipping_usd'],
                'total_usd' => $totals['total_usd'],
                'exchange_rate' => $totals['exchange_rate'],
                'total_syp' => $totals['total_syp'],
                'shipping_method' => $data['shipping_method'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'city' => $data['city'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_proof_path' => $data['payment_proof_path'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_sender_name' => $data['payment_sender_name'] ?? null,
                'coupon_id' => $coupon?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($totals['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item->product->id,
                    'variant_id' => $item->variant?->id,
                    'name_ar' => $item->product->name_ar,
                    'name_en' => $item->product->name_en,
                    'color' => $item->variant?->color,
                    'size' => $item->variant?->size,
                    'quantity' => $item->qty,
                    'unit_price_usd' => $item->unit_usd,
                    'unit_cost_usd' => $item->product->cost_usd,
                    'total_price_usd' => $item->line_usd,
                    'is_wholesale' => $item->is_wholesale,
                ]);

                // خصم المخزون مع توثيق حركة البيع في السجل
                if ($item->variant) {
                    \App\Services\StockService::record(
                        $item->variant->id,
                        'sale',
                        -$item->qty,
                        $order->id,
                        null,
                        'بيع — طلب '.$order->order_code,
                    );
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => __('checkout.order_created'),
                // قد يكون الطلب لضيف بلا حساب — فالسجل يبقى بلا مُنشئ
                'user_id' => $user?->id,
                'created_at' => now(),
            ]);

            if ($coupon) {
                $coupon->increment('used_count');
            }

            return ['order' => $order, 'totals' => $totals];
        });

        $order = $result['order'];
        $totals = $result['totals'];

        CartService::clear();

        // إشعار العميل بالإيميل + إشعار المتجر (تيليجرام/بريد) — فشلها لا يفشل الطلب
        try {
            Notification::send($user, new OrderPlaced($order));
        } catch (\Throwable $e) {
            report($e);
        }
        self::notifyStore($order);

        // تنبيه انخفاض المخزون لقناة الجرد
        foreach ($totals['items'] as $item) {
            if ($item->variant) {
                $item->variant->refresh();
                \App\Services\StockService::checkLowStock($item->variant);
            }
        }

        return $order;
    }

    /** إرسال إشعار المتجر (تيليجرام/بريد) حسب الإعدادات */
    public static function notifyStore(Order $order): void
    {
        if (Setting::bool('notify_telegram_enabled') && Setting::get('telegram_bot_token') && Setting::get('telegram_chat_id')) {
            try {
                TelegramService::sendOrder($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (Setting::bool('notify_email_enabled') && Setting::get('notify_email')) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    self::storeEmailBody($order),
                    fn ($message) => $message
                        ->to(Setting::get('notify_email'))
                        ->subject(__('mail.store_subject', ['code' => $order->order_code]))
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private static function storeEmailBody(Order $order): string
    {
        $lines = [
            "طلب جديد: {$order->order_code}",
            "العميل: {$order->customerName()} ({$order->customerPhone()})",
            '',
        ];
        foreach ($order->items as $item) {
            $lines[] = "- {$item->displayName()} × {$item->quantity} = ".fmt_usd($item->total_price_usd);
        }
        $lines[] = '';
        $lines[] = 'الإجمالي: '.fmt_usd($order->total_usd).' / '.fmt_syp($order->total_syp);
        $lines[] = 'الدفع: '.($order->paymentMethod?->name ?? '-');
        $lines[] = 'التوصيل: '.($order->shipping_method === 'local' ? 'توصيل محلي' : 'استلام من المحل');

        return implode("\n", $lines);
    }
}
