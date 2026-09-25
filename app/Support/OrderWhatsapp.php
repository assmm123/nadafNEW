<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * رسالة الطلب المُرسَلة على واتساب — مصدر الحقيقة الوحيد.
 *
 * ── الصيغة ──
 * ترويسة باسم العميل ورقمه وعنوانه، ثم **مقطع لكل منتج** بنفس الصيغة:
 * الاسم · العدد · الألوان · الكود · التوفّر · رابط المنتج.
 * ولو كان في الطلب عشرة منتجات لظهرت عشرة مقاطع — لا منتج أول ثم «وغيره»،
 * فالمالك يجب أن يعرف **كل** ما طُلب بلا سؤال ثانٍ.
 *
 * ── القاعدتان الموروثتان من رسالة الاستفسار ──
 * ١. الرابط **وحده في سطر** بلا إيموجي ملتصق — وإلا وصل نصًّا لا يُفتح.
 * ٢. والمضيف من `APP_URL` لا من عنوان الطلب — وإلا أرسل من يفتح اللوحة على
 *    `127.0.0.1` رابطًا لا يفتحه أحد.
 */
class OrderWhatsapp
{
    /**
     * ═══ ترويسة الرسالة: اسم العميل ورقمه وعنوانه ═══
     * وهي إلزامية في كل رسالة — فالمالك يجب أن يعرف مع من يتحدث وأين يوصّل.
     *
     * @return array<int, string>
     */
    public static function header(string $name, ?string $phone, ?string $address): array
    {
        return [
            'مرحبًا 👋🏼',
            'الاسم: '.($name !== '' ? $name : '—')
                .' + الرقم: '.($phone ?: '—')
                .' + العنوان: '.($address ?: '—'),
            '',
        ];
    }

    /**
     * ═══ مقطع منتج واحد — الصيغة المطلوبة ═══
     *
     * مصدر واحد لكل الرسائل: رسالة الطلب · رسالة المنتج · رسالة السلة.
     * فلو تغيّرت الصيغة يومًا تغيّرت في الثلاث معًا لا في واحدة.
     *
     * @return array<int, string>
     */
    public static function itemBlock(
        string $name,
        int $quantity,
        ?string $color,
        string $code,
        string $availability,
        string $url,
    ): array {
        return [
            '━━━━━━━━━━━━━',
            'أستفسر عن هذا المنتج:',
            'الاسم: '.$name,
            '1: العدد: '.$quantity,
            '2: الألوان: '.(trim((string) $color) !== '' ? $color : '—'),
            '3: الكود: '.$code,
            '4: التوفّر: '.$availability,
            // ⚠️ الرابط وحده في سطر بلا إيموجي ملتصق — وإلا وصل نصًّا لا يُفتح
            '5: رابط المنتج:',
            $url,
        ];
    }

    /**
     * مقطع منتج من موديله — يجمع الكود والتوفّر والرابط.
     *
     * و`$name` يُمرَّر صراحةً في رسالة الطلب: بنية الطلب تحفظ **اسم المنتج
     * لحظة الشراء**، وهو المرجع الصحيح في فاتورة. ولو قُرئ الاسم الحالي
     * لتغيّر مستند قديم كلما أُعيد تسمية المنتج.
     */
    public static function productBlock(Product $product, ?ProductVariant $variant, int $quantity, ?string $name = null): array
    {
        return self::itemBlock(
            $name ?: (string) $product->name_ar,
            $quantity,
            $variant?->color,
            self::code($product, $variant?->color, $variant?->size),
            self::availability($product),
            self::productUrl($product),
        );
    }

    /**
     * رسالة منتج واحد — تُستخدم في زر واتساب بصفحة المنتج.
     *
     * @param  array{name: string, phone: ?string, address: ?string}  $customer
     */
    public static function forProduct(Product $product, ?ProductVariant $variant, int $quantity, array $customer): string
    {
        $lines = self::header($customer['name'] ?? '', $customer['phone'] ?? null, $customer['address'] ?? null);
        $lines = array_merge($lines, self::productBlock($product, $variant, $quantity));

        return implode("\n", $lines);
    }

    /**
     * رسالة السلة — **مقطع منفصل لكل منتج**، لا منتج أول ثم «وغيره».
     *
     * @param  iterable<int, object>  $items  عناصر CartService::totals()['items']
     * @param  array{name: string, phone: ?string, address: ?string}  $customer
     */
    public static function forCart(iterable $items, array $customer): string
    {
        $lines = self::header($customer['name'] ?? '', $customer['phone'] ?? null, $customer['address'] ?? null);

        foreach ($items as $item) {
            if (! $item->product) {
                continue;
            }

            $lines = array_merge($lines, self::productBlock($item->product, $item->variant, (int) $item->qty));
        }

        return implode("\n", $lines);
    }

    /** الرسالة الكاملة لطلب — تُستخدم في زر «إتمام الطلب + إرسال واتساب» */
    public static function message(Order $order): string
    {
        $order->loadMissing(['items.product.variants', 'items.product.media']);

        $lines = self::header(
            $order->customerName(),
            $order->customerPhone(),
            self::address($order),
        );

        $lines[] = '🛒 كود الطلب: '.$order->order_code;

        foreach ($order->items as $item) {
            $lines[] = '';
            $lines = array_merge($lines, self::productBlock(
                $item->product ?? new Product(['name_ar' => $item->name_ar, 'slug' => '']),
                $item->product?->variants->first(fn (ProductVariant $v) => (string) $v->color === (string) $item->color
                    && (string) $v->size === (string) $item->size),
                (int) $item->quantity,
                // الاسم كما شُري لا كما هو الآن
                (string) $item->name_ar,
            ));
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━';
        $lines[] = '*الإجمالي: '.fmt_usd($order->total_usd).' / '.fmt_syp($order->total_syp).'*';

        return implode("\n", $lines);
    }

    /** رابط واتساب جاهز بالرسالة — إلى رقم المتجر، وإلا يفتح اختيار المحادثة */
    public static function url(Order $order): string
    {
        return whatsapp_inquiry_link(self::message($order));
    }

    private static function address(Order $order): string
    {
        $parts = array_filter([$order->city, $order->shipping_address]);

        return $parts
            ? implode(' — ', $parts)
            : ($order->shipping_method === 'pickup' ? 'استلام من المتجر' : '—');
    }

    /**
     * الكود: رمز الصنف إن وُجد، وإلا الكود الداخلي للمنتج.
     * والرمز الداخلي أهمّ للمالك — به يجد القطعة في المخزون.
     */
    private static function code(?Product $product, ?string $color, ?string $size): string
    {
        if (! $product) {
            return '—';
        }

        $variant = $product->variants
            ->first(fn (ProductVariant $v) => (string) $v->color === (string) $color
                && (string) $v->size === (string) $size);

        return $variant?->sku ?: ($product->internal_code ?: '—');
    }

    private static function availability(?Product $product): string
    {
        if (! $product) {
            return '—';
        }

        $quantity = (int) ProductVariant::where('product_id', $product->id)->sum('quantity');

        return $quantity > 0 ? 'متوفّر' : 'غير متوفّر حاليًا';
    }

    /** نفس قاعدة رابط الاستفسار: المضيف من `APP_URL` والمسار من المسار المسمّى */
    private static function productUrl(?Product $product): string
    {
        return $product ? ProductInquiry::url($product) : '—';
    }
}
