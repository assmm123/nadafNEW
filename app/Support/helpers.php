<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('current_exchange_rate')) {
    function current_exchange_rate(): float
    {
        return (float) setting('exchange_rate', 15000);
    }
}

if (! function_exists('syp_from_usd')) {
    /** تحويل الدولار إلى ليرة مقرّبة لأقرب 100 */
    function syp_from_usd(float $usd): float
    {
        return round($usd * current_exchange_rate() / 100) * 100;
    }
}

if (! function_exists('fmt_usd')) {
    function fmt_usd($amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }
}

if (! function_exists('fmt_syp')) {
    function fmt_syp($amount): string
    {
        return app()->getLocale() === 'en'
            ? 'SYP '.number_format((float) $amount, 0)
            : number_format((float) $amount, 0).' ل.س';
    }
}

if (! function_exists('fmt_price')) {
    /** عرض السعر بالعملة المختارة في الجلسة */
    function fmt_price(float $usd, ?float $syp = null): string
    {
        $syp = $syp ?? syp_from_usd($usd);

        return session('currency', 'usd') === 'syp' ? fmt_syp($syp) : fmt_usd($usd);
    }
}

if (! function_exists('media_duration')) {
    /** مدة ملف فيديو بالثواني عبر getID3 — 0 عند الفشل */
    function media_duration(string $absolutePath): float
    {
        try {
            $info = (new \getID3)->analyze($absolutePath);

            return (float) ($info['playtime_seconds'] ?? 0);
        } catch (Throwable) {
            return 0.0;
        }
    }
}

if (! function_exists('status_badge_class')) {
    function status_badge_class(string $status): string
    {
        return match (App\Models\Order::statusColor($status)) {
            'success' => 'bg-green-100 text-green-800',
            'danger' => 'bg-red-100 text-red-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'info' => 'bg-sky-100 text-sky-800',
            // 'primary' كانت indigo (بنفسجي) — صارت ذهبية من هوية المتجر.
            // قيم صريحة لا رموز، لأن هذه الأصناف تُستخدم في اللوحة والمتجر
            // وكل واحد له بناء Tailwind مستقل.
            'primary' => 'bg-[#F3EAD3] text-[#8A6C22]',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}

if (! function_exists('inquiry_mode')) {
    /** وضع الاستفسار — إخفاء كامل للأسعار واستبدالها بوسائل تواصل */
    function inquiry_mode(): bool
    {
        return Setting::bool('hide_prices') || Setting::get('price_display_mode') === 'whatsapp';
    }
}

if (! function_exists('price_mode')) {
    /**
     * وضع عرض الأسعار: both | second_big | normal_big | whatsapp
     */
    function price_mode(): string
    {
        if (Setting::bool('hide_prices')) {
            return 'whatsapp';
        }

        return Setting::get('price_display_mode', 'both');
    }

    /** إظهار السعر العادي؟ */
    function show_normal_price(): bool
    {
        return in_array(price_mode(), ['both', 'normal_big'], true);
    }

    /** إظهار سعر الجملة/السعر الثاني؟ */
    function show_wholesale_price(): bool
    {
        return in_array(price_mode(), ['both', 'second_big'], true);
    }

    /** إخفاء كل الأسعار نهائيًا بلا بديل (وضع none) */
    function hide_all_prices(): bool
    {
        return price_mode() === 'none';
    }
}

if (! function_exists('wa_digits')) {
    /**
     * رقم واتساب بصيغة دولية صالحة لـwa.me — بلا بادئة اتصال دولي وبلا صفر محلي.
     *
     * `wa.me` يريد `963930322406`. وهناك شكلان مألوفان عندنا كلاهما لا يفتح:
     *   • `00963930322406` — بادئة اتصال دولي، تُزال بلا شرط.
     *   • `0987654365`    — رقم محلي. و`wa.me/0987654365` **لا يفتح شيئًا**
     *                        لأن أول خانتين تُقرآن مفتاحَ دولة. فيُستبدل صفره
     *                        بمفتاح الدولة من الإعدادات (`wa_country_code`).
     * والاستبدال لا يقع إلا إن كان المفتاح مضبوطًا صراحةً في الإعدادات —
     * لا استنتاجًا ولا تخمينًا: مفتاح خاطئ أسوأ من رابط لا يفتح.
     */
    function wa_digits(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $code = preg_replace('/\D/', '', (string) setting('wa_country_code', ''));

            if ($code !== '') {
                return $code.ltrim($digits, '0');
            }
        }

        return $digits;
    }
}

if (! function_exists('whatsapp_inquiry_link')) {
    /** رابط واتساب جاهز بنص مسبق — رقم الواتس من الإعدادات، وإلا أول وسيلة واتساب مفعلة، وإلا هاتف المتجر */
    function whatsapp_inquiry_link(string $text = ''): string
    {
        $digits = wa_digits(setting('whatsapp_number', ''));

        if ($digits === '') {
            $method = App\Models\CommunicationMethod::where('type', 'whatsapp')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();
            $digits = $method ? wa_digits($method->value) : '';
        }

        if ($digits === '') {
            $digits = wa_digits(setting('store_phone', ''));
        }

        $base = $digits !== '' ? "https://wa.me/{$digits}" : 'https://wa.me/';

        return $text !== '' ? $base.'?text='.rawurlencode($text) : $base;
    }
}

if (! function_exists('product_price_hidden')) {
    /**
     * هل يُخفى **كل** سعر هذا المنتج عن العميل؟
     *
     * الدمج: الإعداد العام (وضع none أو وضع الاستفسار) **أو** علم المنتج.
     * والعام يتقدّم دائمًا — علم المنتج يزيد إخفاءً ولا يلغيه أبدًا،
     * فلا يستطيع منتج واحد أن يُظهر الأسعار في متجر أُخفيت فيه كليًا.
     */
    function product_price_hidden(?App\Models\Product $product): bool
    {
        return hide_all_prices() || inquiry_mode() || (bool) $product?->hidesPrice();
    }
}

if (! function_exists('product_shows_unit_price')) {
    /** هل يظهر السعر العادي (سعر القطعة)؟ */
    function product_shows_unit_price(?App\Models\Product $product): bool
    {
        return ! product_price_hidden($product) && ! (bool) $product?->hidesUnitPrice();
    }
}

if (! function_exists('product_shows_wholesale')) {
    /** هل يظهر سعر الجملة؟ يحتاج سعرًا مضبوطًا + ألا يكون مخفيًا عامًّا أو خاصًّا */
    function product_shows_wholesale(?App\Models\Product $product): bool
    {
        return ! product_price_hidden($product) && (bool) $product?->showsWholesale();
    }
}

if (! function_exists('product_shows_colors')) {
    /** هل تُعرض الألوان والمقاسات للعميل؟ */
    function product_shows_colors(?App\Models\Product $product): bool
    {
        return ! (bool) $product?->hidesColors();
    }
}
