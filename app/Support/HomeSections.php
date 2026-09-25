<?php

namespace App\Support;

use App\Models\Setting;

/**
 * أقسام الصفحة الرئيسية وترتيبها.
 *
 * الترتيب يُقرأ من إعداد home_sections. والقواعد:
 *   • أي مفتاح مجهول في الإعداد يُهمَل (لا خطأ لو حُذف قسم من الكود)
 *   • أي قسم غير مذكور في الإعداد يُلحَق في آخر الصفحة (قسم جديد يظهر تلقائيًا)
 *   • لا تكرار ولا مفاتيح مكرّرة
 *
 * بهذا يتحكم المالك بالترتيب من اللوحة، وإضافة قسم جديد في الكود لا تتطلب
 * تعديل أي بيانات قائمة.
 */
final class HomeSections
{
    /** المفتاح => الاسم الظاهر في اللوحة */
    public const SECTIONS = [
        'hero' => 'السلايدر وتعريف المنصة',
        'categories' => 'الأقسام',
        'latest' => 'أحدث المنتجات',
        'trust' => 'شريط الثقة',
    ];

    /** الترتيب المعتمد افتراضيًا */
    public const DEFAULT = ['hero', 'categories', 'latest', 'trust'];

    /** الأقسام بالترتيب الفعلي */
    public static function active(): array
    {
        $ordered = [];

        foreach (self::saved() as $key) {
            if (isset(self::SECTIONS[$key]) && ! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        foreach (array_keys(self::SECTIONS) as $key) {
            if (! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    /** خيارات القائمة في اللوحة */
    public static function options(): array
    {
        return self::SECTIONS;
    }

    /**
     * قراءة الإعداد بمرونة: يقبل JSON، أو مصفوفة، أو مصفوفة صفوف
     * من Repeater بالشكل [['section' => 'hero'], ...].
     */
    private static function saved(): array
    {
        $raw = Setting::get('home_sections');

        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return self::DEFAULT;
        }

        $keys = [];

        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = $item['section'] ?? null;
            }

            if (is_string($item) && $item !== '') {
                $keys[] = $item;
            }
        }

        return $keys === [] ? self::DEFAULT : $keys;
    }
}
