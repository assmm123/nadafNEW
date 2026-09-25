<?php

namespace App\Support;

use App\Models\CommunicationMethod;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * رسالة الاستفسار عن منتج — مصدر الحقيقة الوحيد.
 *
 * ── العلّة التي تحلّها ──
 * كان زر الاستفسار يفتح المحادثة **فارغة تمامًا**: `CommunicationMethod::link()`
 * تُنتج رابط قناة مجرّدًا بلا نصّ (`wa.me/رقم` بلا `?text=`)، والمسار الاحتياطي
 * وحده كان يحمل رسالة — **باسم المنتج فقط**. فيصل المالك إلى محادثة بلا بيان،
 * ويسأل «أي منتج؟».
 *
 * ── القاعدتان ──
 * ١. الرسالة تُبنى من **القاعدة** لا من الشاشة: الاسم والرمز والمواصفة والسعر
 *    والتوفّر، ومعها **رابط مباشر** يفتح صفحة المنتج من أي تطبيق.
 * ٢. ⚠️ **السعر لا يُدرج حين يكون مخفيًّا.** وضع «السعر عند التواصل» يخفي السعر
 *    عمدًا؛ وإدراجه في الرسالة **يسرّب ما نُخفيه** وينقض الغرض من الوسيلة كلها.
 *    فالمessage يتبع وضع العرض ولا يتجاوزه.
 */
class ProductInquiry
{
    /**
     * الرسالة الكاملة عن منتج — تُستخدم في كل قنوات الاستفسار.
     */
    public static function message(Product $product, ?ProductVariant $variant = null): string
    {
        $lines = ['مرحبًا 👋 أستفسر عن هذا المنتج:', ''];

        $lines[] = '▪ الاسم: '.self::name($product);

        if (filled($product->internal_code)) {
            $lines[] = '▪ الرمز: '.$product->internal_code;
        }

        if ($variant) {
            $spec = trim(($variant->color ?? '').' '.($variant->size ?? ''));

            if ($spec !== '') {
                $lines[] = '▪ المواصفة: '.$spec;
            }

            if (filled($variant->sku)) {
                $lines[] = '▪ رمز الصنف: '.$variant->sku;
            }
        } else {
            // بلا صنف محدَّد: نُدرج المتاح إجمالًا فيعرف المالك بماذا يرد بدقة
            // بدل أن يبدأ سؤالًا ثانيًا عن المقاسات.
            foreach (self::availableSpecs($product) as $label => $values) {
                $lines[] = "▪ {$label}: ".$values;
            }
        }

        // السعر — بشرط ألا يكون المتجر يخفيه
        if (self::showsPrice($product)) {
            $lines[] = '▪ السعر: '.self::price($product);
        }

        $lines[] = '▪ التوفّر: '.self::availability($product, $variant);

        // ⚠️ الرابط وحده على سطر مستقل، بلا إيموجي ولا علامة ملتصقة به.
        // واتساب يحوّل الروابط إلى نصّ قابل للنقر تلقائيًا، لكن الرمز الملتصق
        // قبل الرابط قد يمنع ذلك — وقد وصل المالك رابطًا **نصًّا لا يُفتح**.
        // والملصق في سطر منفصل فوقه حتى لا يلتصق بالرابط.
        $lines[] = '';
        $lines[] = 'رابط المنتج:';
        $lines[] = self::url($product);

        return implode("\n", $lines);
    }

    /** رابط واتساب جاهز بالرسالة الكاملة — القناة الوحيدة المستخدمة حاليًا */
    public static function whatsappUrl(Product $product, ?ProductVariant $variant = null): string
    {
        return whatsapp_inquiry_link(self::message($product, $variant));
    }

    /**
     * هل يوجد رقم واتساب فعلي؟
     *
     * `whatsapp_inquiry_link` تعود إلى `https://wa.me/` بلا رقم إن لم يُضبط
     * شيء — رابط يقود إلى لا شيء. فنفحص قبل عرض الزر بدل أن نعرض زرًّا ميتًا.
     */
    public static function hasWhatsapp(): bool
    {
        $candidates = [
            (string) setting('whatsapp_number', ''),
            (string) setting('store_phone', ''),
        ];

        foreach (CommunicationMethod::where('type', 'whatsapp')->where('is_active', true)->pluck('value') as $value) {
            $candidates[] = (string) $value;
        }

        foreach ($candidates as $candidate) {
            if (wa_digits($candidate) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * الرابط المطلق لصفحة المنتج.
     *
     * ⚠️ المضيف يُبنى من `APP_URL` لا من عنوان الطلب — وهذا مقصود.
     * فمن يفتح اللوحة على `127.0.0.1` كانت روابطه تُبنى بـ`127.0.0.1`، وهي
     * **لا تُفتح من هاتف إطلاقًا**، فيصل رابط الاستفسار على واتساب ميتًا.
     *
     * ولم يُثبَّت المضيف عالميًا بـ`URL::forceRootUrl` لأن ذلك **يكسر الدخول**:
     * التحويلات تصير مطلقة إلى `APP_URL`، فيُحوَّل المتصفح إلى مضيف آخر
     * وكوكي الجلسة على المضيف الأول — فيجد المستخدم نفسه خارج حسابه.
     * فالتثبيت هنا **موضعي**: لهذا الرابط وحده، وبقية التطبيق تبقى على
     * المضيف الذي يتصفّحه المستخدم فعلًا.
     */
    public static function url(Product $product): string
    {
        $path = route('product.show', $product->slug, absolute: false);
        $root = rtrim((string) config('app.url'), '/');

        return $root !== '' ? $root.$path : route('product.show', $product->slug);
    }

    public static function name(Product $product): string
    {
        $en = trim((string) $product->name_en);

        return $en !== '' && $en !== $product->name_ar
            ? $product->name_ar.' / '.$en
            : (string) $product->name_ar;
    }

    /**
     * هل يجوز إدراج السعر في الرسالة؟
     *
     * ثلاثة شروط لا واحد: ألا يكون المتجر في وضع الاستفسار، ألا يكون السعر
     * مخفيًّا لهذا المنتج، وأن يكون سعر الوحدة معروضًا أصلًا.
     */
    public static function showsPrice(Product $product): bool
    {
        return ! inquiry_mode()
            && ! product_price_hidden($product)
            && product_shows_unit_price($product);
    }

    /** السعر بالعملتين — أوضح في المحادثة من عملة واحدة */
    public static function price(Product $product): string
    {
        return fmt_usd($product->price_usd).' ('.fmt_syp(syp_from_usd($product->price_usd)).')';
    }

    /** «متوفّر» لا رقمًا: الكمية الدقيقة شأن داخلي لا يُذاع في محادثة */
    public static function availability(Product $product, ?ProductVariant $variant = null): string
    {
        $quantity = $variant
            ? (int) $variant->quantity
            : (int) ProductVariant::where('product_id', $product->id)->sum('quantity');

        return $quantity > 0 ? 'متوفّر' : 'غير متوفّر حاليًا';
    }

    /**
     * الألوان والمقاسات المتاحة — بلا تكرار ومحدودة الطول.
     *
     * الحدّ مقصود: منتج بعشرين لونًا يجعل الرسالة تطول فينفر العميل من
     * إرسالها. وما زاد عن الحدّ يُختصر بعبارة صريحة لا يُبتَر بصمت.
     *
     * @return array<string, string>
     */
    public static function availableSpecs(Product $product): array
    {
        $limit = 6;
        $out = [];

        foreach (['الألوان' => 'color', 'المقاسات' => 'size'] as $label => $column) {
            $values = $product->variants
                ->pluck($column)
                ->filter(fn ($v) => filled($v))
                ->unique()
                ->values();

            if ($values->isEmpty()) {
                continue;
            }

            $shown = $values->take($limit)->implode('، ');
            $out[$label] = $values->count() > $limit
                ? $shown.' و'.($values->count() - $limit).' غيرها'
                : $shown;
        }

        return $out;
    }
}
