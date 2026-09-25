<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    public const SESSION_KEY = 'cart';

    /**
     * عناصر السلة الخام من الجلسة.
     * المفاتيح: "v{variantId}" لمنتج له متغيرات، "p{productId}" لمنتج بلا متغيرات.
     */
    public static function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /** أقصى كمية للعنصر الواحد في المنتجات بلا متغيرات (قابل للضبط من الإعدادات) */
    public static function maxQtyPerItem(): int
    {
        return max(1, (int) Setting::get('max_qty_per_item', 99));
    }

    /**
     * أقصى كمية مسموحة لعنصر سلة محدد.
     *
     * منتج له متغيرات → المخزون الفعلي (الأولوية للمخزون).
     * منتج بلا متغيرات → إعداد max_qty_per_item.
     *
     * تُستخدم في add() و update() معًا — كان update() يطبّق السقف على
     * المتغيرات فقط، فمرّت أي كمية عبره بلا حد.
     */
    public static function maxQtyFor(?array $item): int
    {
        $variant = $item['variant'] ?? null;

        if ($variant instanceof ProductVariant && $variant->exists) {
            return (int) $variant->quantity;
        }

        return self::maxQtyPerItem();
    }

    public static function add(string $key, int $qty = 1): void
    {
        $item = self::resolveItem($key);
        abort_if($item === null, 404);

        $cart = self::raw();
        $current = $cart[$key]['qty'] ?? 0;
        $max = self::maxQtyFor($item);
        $newQty = min($current + max(1, $qty), $max);

        $cart[$key] = ['key' => $key, 'qty' => $newQty];
        Session::put(self::SESSION_KEY, $cart);
        self::trackAbandoned();
    }

    public static function update(string $key, int $qty): void
    {
        $cart = self::raw();
        if (! isset($cart[$key])) {
            return;
        }

        if ($qty < 1) {
            unset($cart[$key]);
            Session::put(self::SESSION_KEY, $cart);

            return;
        }

        // نفس سقف add() تمامًا: المخزون الفعلي للمتغير، وإعداد max_qty_per_item
        // لما بلا متغيرات. الفرق السابق بين المسارين كان يسمح بكمية بلا حد.
        $qty = min($qty, self::maxQtyFor(self::resolveItem($key)));

        $cart[$key]['qty'] = max(1, $qty);
        Session::put(self::SESSION_KEY, $cart);
    }

    public static function remove(string $key): void
    {
        $cart = self::raw();
        unset($cart[$key]);
        Session::put(self::SESSION_KEY, $cart);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget('coupon_code');
        AbandonedCartTracker::forget();
    }

    /** تسجيل حالة السلة لرصد السلات المتروكة (يُستدعى بعد كل تغيير) */
    public static function trackAbandoned(): void
    {
        AbandonedCartTracker::track();
    }

    public static function count(): int
    {
        return array_sum(array_column(self::raw(), 'qty'));
    }

    /** عناصر السلة كاملة التفاصيل مع الأسعار ومنطق الجملة */
    public static function detailed(): Collection
    {
        $raw = self::raw();
        if (empty($raw)) {
            return collect();
        }

        $variantKeys = array_values(array_filter(array_keys($raw), fn ($k) => str_starts_with($k, 'v')));
        $productKeys = array_values(array_filter(array_keys($raw), fn ($k) => str_starts_with($k, 'p')));

        $variants = ProductVariant::with(['product.category'])
            ->whereIn('id', array_map(fn ($k) => (int) substr($k, 1), $variantKeys))
            ->get()
            ->mapWithKeys(fn ($v) => ["v{$v->id}" => $v]);

        $products = Product::with(['category'])
            ->whereIn('id', array_map(fn ($k) => (int) substr($k, 1), $productKeys))
            ->get()
            ->mapWithKeys(fn ($p) => ["p{$p->id}" => $p]);

        $minQty = (int) Setting::get('wholesale_min_quantity', 10);
        $minAmount = (float) Setting::get('wholesale_min_amount_usd', 200);

        // هل مجموع السلة (بأسعار المفرق) يبلغ الحد الأدنى للجملة؟
        $regularSubtotal = 0;
        foreach (array_keys($raw) as $key) {
            $product = $variants[$key]?->product ?? $products[$key] ?? null;
            if ($product) {
                $regularSubtotal += $product->price_usd * $raw[$key]['qty'];
            }
        }
        $amountReached = $minAmount > 0 && $regularSubtotal >= $minAmount;

        return collect(array_keys($raw))->map(function (string $key) use ($raw, $variants, $products, $minQty, $amountReached) {
            $variant = $variants[$key] ?? null;
            $product = $variant?->product ?? $products[$key] ?? null;
            if (! $product) {
                return null;
            }

            $qty = $raw[$key]['qty'];

            $isWholesale = $product->wholesale_price_usd
                && ! $product->hide_wholesale
                && ! Setting::bool('hide_wholesale_button')
                && ($qty >= $minQty || $amountReached);

            $unitUsd = $product->unitPriceUsd($isWholesale);

            return (object) [
                'key' => $key,
                'variant' => $variant,
                'product' => $product,
                'qty' => $qty,
                'is_wholesale' => $isWholesale,
                'unit_usd' => $unitUsd,
                'unit_syp' => $product->priceSyp($isWholesale),
                'line_usd' => round($unitUsd * $qty, 2),
                'variant_label' => $variant?->label(),
            ];
        })->filter();
    }

    /**
     * حساب الإجماليات الكاملة.
     * $shippingMethod: null (قبل الدفع) | 'pickup' | 'local'
     */
    public static function totals(?string $shippingMethod = null, ?Coupon $coupon = null): array
    {
        $items = self::detailed();

        $subtotal = round($items->sum('line_usd'), 2);

        $discount = 0.0;
        if ($coupon && $coupon->isValid($subtotal)) {
            $discount = $coupon->discountFor($subtotal);
        }

        $shipping = 0.0;
        if ($shippingMethod === 'local' && Setting::bool('shipping_enabled', true)) {
            $shipping = (float) Setting::get('shipping_fee_usd', 0);
        }

        $total = round(max(0, $subtotal - $discount) + $shipping, 2);

        return [
            'items' => $items,
            'subtotal_usd' => $subtotal,
            'discount_usd' => round($discount, 2),
            'shipping_usd' => round($shipping, 2),
            'total_usd' => $total,
            'total_syp' => syp_from_usd($total),
            'exchange_rate' => current_exchange_rate(),
            'has_wholesale' => $items->contains(fn ($i) => $i->is_wholesale),
        ];
    }

    /** التحقق من توفر الكميات قبل إنشاء الطلب — يعيد أخطاء إن وجدت */
    public static function stockErrors(): array
    {
        $errors = [];
        foreach (self::detailed() as $item) {
            if ($item->variant && $item->variant->quantity < $item->qty) {
                $errors[] = __('cart.insufficient_stock', ['name' => $item->product->name]);
            }
        }

        return $errors;
    }

    /** جلب المنتج/المتغير من مفتاح السلة — للتحقق قبل الإضافة */
    private static function resolveItem(string $key): ?array
    {
        if (str_starts_with($key, 'v')) {
            $variant = ProductVariant::with('product')->find((int) substr($key, 1));
            if (! $variant || ! $variant->product->is_active) {
                return null;
            }

            return ['variant' => $variant, 'product' => $variant->product];
        }

        if (str_starts_with($key, 'p')) {
            $product = Product::find((int) substr($key, 1));
            if (! $product || ! $product->is_active) {
                return null;
            }

            return ['variant' => null, 'product' => $product];
        }

        return null;
    }
}
