<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «+ طلب يدوي» — إنشاء طلب من اللوحة.
 *
 * الحاجة: كثير من الطلبات تصل هاتفًا أو من زائر في المحل، لا من الموقع.
 * والنظام يرفض `orders.user_id = null` (العمود إلزامي)، فالعميل الهاتفي
 * يُنشأ له حساب عميل خفيف بلا كلمة سر تُستخدم — ويمكن ترقيته لحساب كامل
 * لاحقًا بمجرد «نسيت كلمة السر».
 *
 * وهو **يعيد استخدام نفس خدمات الموقع** لا يكرّرها:
 *   • StockService::record  → حركة بيع موثّقة في سجل المخزون
 *   • Order::generateCode   → نفس صيغة NDF-XXXXXX
 *   • syp_from_usd + current_exchange_rate → نفس تثبيت سعر الصرف
 * فمحاسبة الطلب اليدوي والطلب الإلكتروني متطابقة تمامًا.
 */
final class ManualOrderService
{
    /** بريد حساب «عميل نقدي» المشترك — بيع مباشر بلا أي بيانات عميل */
    private const WALK_IN_EMAIL = 'walkin@nadaf.local';

    /**
     * @param  array{
     *     user_id?: int|null,
     *     customer_name?: string, customer_phone?: string, customer_email?: string,
     *     items: array<int, array{variant_id:int, quantity:int, unit_price_usd?:float|string|null, is_wholesale?:bool}>,
     *     shipping_method?: string, shipping_address?: string|null, city?: string|null, shipping_usd?: float,
     *     discount_usd?: float, payment_method_id?: int|null, payment_reference?: string|null,
     *     payment_sender_name?: string|null, notes?: string|null, confirm_payment?: bool
     * }  $data
     */
    public static function place(array $data): Order
    {
        $rows = collect($data['items'] ?? [])
            ->filter(fn ($i) => ! empty($i['variant_id']) && (int) ($i['quantity'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'أضف منتجًا واحدًا على الأقل بكمية أكبر من صفر.',
            ]);
        }

        return DB::transaction(function () use ($data, $rows) {
            $user = self::resolveCustomer($data);

            // اقفل صفوف المتغيّرات ثم تحقق من التوفّر — كما في CheckoutService،
            // فلا يمكن لطلب يدوي أن يبيع آخر قطعة في اللحظة نفسها التي يبيعها الموقع.
            $variantIds = $rows->map(fn ($r) => (int) $r['variant_id'])->unique()->values()->all();

            $variants = ProductVariant::whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $errors = [];

            foreach ($rows as $row) {
                $variant = $variants->get((int) $row['variant_id']);

                if (! $variant) {
                    $errors[] = 'متغيّر غير موجود (#'.(int) $row['variant_id'].').';

                    continue;
                }

                $qty = (int) $row['quantity'];
                $available = (int) $variant->quantity;

                if ($available < $qty) {
                    $errors[] = trim(
                        ($variant->product?->name_ar ?? 'منتج')
                        .' ('.$variant->label().'): المتوفر '.$available.' فقط.'
                    );

                    continue;
                }

                $product = $variant->product;
                $isWholesale = (bool) ($row['is_wholesale'] ?? false);

                // سعر مُدخل يدويًا يتقدّم على سعر المنتج — لأن البيع الهاتفي
                // كثيرًا ما يُتفاوض عليه. وإن تُرك فارغًا يُؤخذ سعر المنتج.
                $override = $row['unit_price_usd'] ?? null;
                $unit = ($override === null || $override === '')
                    ? (float) $product?->unitPriceUsd($isWholesale)
                    : round((float) $override, 2);

                $lines[] = [
                    'variant' => $variant,
                    'product' => $product,
                    'qty' => $qty,
                    'unit_usd' => $unit,
                    'line_usd' => round($unit * $qty, 2),
                    'is_wholesale' => $isWholesale,
                ];
            }

            if ($errors) {
                throw ValidationException::withMessages(['items' => implode(' ', $errors)]);
            }

            $subtotal = round(collect($lines)->sum('line_usd'), 2);
            $discount = round((float) ($data['discount_usd'] ?? 0), 2);
            $method = $data['shipping_method'] ?? 'pickup';
            // الاستلام من المحل لا أجرة عليه — نفس قاعدة الموقع
            $shipping = $method === 'local' ? round((float) ($data['shipping_usd'] ?? 0), 2) : 0.0;
            $total = round(max(0, $subtotal - $discount) + $shipping, 2);

            $rate = current_exchange_rate();

            $order = Order::create([
                'user_id' => $user->id,
                'order_code' => Order::generateCode(),
                'status' => 'pending',
                'subtotal_usd' => $subtotal,
                'discount_usd' => $discount,
                'shipping_usd' => $shipping,
                'total_usd' => $total,
                'exchange_rate' => $rate,
                'total_syp' => syp_from_usd($total),
                'shipping_method' => $method,
                'shipping_address' => $data['shipping_address'] ?? null,
                'city' => $data['city'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_sender_name' => $data['payment_sender_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $variant = $line['variant'];

                $order->items()->create([
                    'product_id' => $product?->id,
                    'variant_id' => $variant->id,
                    'name_ar' => $product?->name_ar ?? 'منتج',
                    'name_en' => $product?->name_en ?? 'Product',
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'quantity' => $line['qty'],
                    'unit_price_usd' => $line['unit_usd'],
                    'unit_cost_usd' => $product?->cost_usd,
                    'total_price_usd' => $line['line_usd'],
                    'is_wholesale' => $line['is_wholesale'],
                ]);

                StockService::record(
                    $variant->id,
                    'sale',
                    -$line['qty'],
                    $order->id,
                    null,
                    'بيع — طلب يدوي '.$order->order_code,
                );
            }

            $actor = auth()->user()?->name ?? 'النظام';

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'أُنشئ الطلب يدويًا من اللوحة بواسطة '.$actor,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);

            // «مقبوض» اختياري عند الإنشاء — للعميل الذي دفع في المحل مباشرة.
            // نفس أثر زر «تم قبض الدفع»: ختم أخضر + انتقال إلى «قيد التحضير».
            if ($data['confirm_payment'] ?? false) {
                $order->update([
                    'payment_confirmed_at' => now(),
                    'payment_confirmed_by' => auth()->id(),
                    'status' => 'preparing',
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => 'pending',
                    'to_status' => 'preparing',
                    'note' => 'تم قبض الدفع (الختم الأخضر) بواسطة '.$actor,
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                ]);
            }

            return $order;
        });
    }

    /**
     * عميل الطلب — والتفاصيل كلها **اختيارية**.
     *
     * الترتيب: عميل مختار ← بريد مطابق ← هاتف مطابق ← حساب «عميل نقدي».
     * ولا نُنشئ حسابًا مكرّرًا في أي حال.
     */
    private static function resolveCustomer(array $data): User
    {
        if (! empty($data['user_id'])) {
            return User::findOrFail($data['user_id']);
        }

        $name = trim((string) ($data['customer_name'] ?? ''));
        $phone = trim((string) ($data['customer_phone'] ?? ''));
        $email = trim((string) ($data['customer_email'] ?? ''));

        // ١) بريد مطابق ⇒ هذا العميل نفسه.
        //    كان غياب هذا الفحص سبب خطأ 500:
        //    UNIQUE constraint failed: users.email — لأن البريد عمود فريد،
        //    فكتابة بريد عميل مسجّل كانت تحاول إنشاء حساب به فتُسقط الطلب.
        if ($email !== '') {
            $existing = User::where('email', $email)->first();

            if ($existing) {
                return $existing;
            }
        }

        // ٢) هاتف مطابق ⇒ هذا العميل نفسه (ولا نُنشئ حسابًا مكرّرًا)
        if ($phone !== '') {
            $existing = User::where('phone', $phone)->first();

            if ($existing) {
                return $existing;
            }
        }

        // ٣) بلا أي بيانات ⇒ حساب نقدي مشترك (بيع مباشر في المحل)
        if ($name === '' && $phone === '' && $email === '') {
            return self::walkInCustomer();
        }

        if ($email === '') {
            // العمودان إلزاميان وفريدان، والطلب الهاتفي لا بريد له.
            // النطاق .local غير قابل للتوجيه فلا يصل إليه بريد بالخطأ.
            $email = 'phone-'
                .(preg_replace('/\D/', '', $phone) ?: Str::lower(Str::random(6)))
                .'-'.Str::lower(Str::random(4))
                .'@nadaf.local';
        }

        try {
            return User::create([
                'name' => $name !== '' ? $name : 'عميل نقدي',
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email,
                'password' => Str::random(24),
                'role' => 'customer',
                'status' => 'active',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // سباق نادر: أُنشئ الحساب بين الفحص والإنشاء ⇒ نستخدمه بدل السقوط
            return User::where('email', $email)->firstOrFail();
        }
    }

    /** حساب مشترك لكل طلب بلا أي بيانات عميل — بيع نقدي مباشر في المحل */
    private static function walkInCustomer(): User
    {
        return User::firstOrCreate(
            ['email' => self::WALK_IN_EMAIL],
            [
                'name' => 'عميل نقدي',
                'phone' => null,
                'password' => Str::random(32),
                'role' => 'customer',
                'status' => 'active',
            ],
        );
    }
}
