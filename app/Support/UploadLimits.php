<?php

namespace App\Support;

/**
 * حدود الرفع الفعلية كما يفرضها PHP — لا كما نتمنّاها.
 *
 * الحاجة: فيديو يصل إلى دقيقة قد يبلغ عشرات الميغابايت، وحدود PHP الافتراضية
 * (12M/16M) ترفضه. والأسوأ أن الرفض يقع **قبل** وصول الطلب إلى Laravel، فلا
 * يرى المالك سببًا. هذه الفئة تقرأ الحدّ الحقيقي ليُعرض في اللوحة ويُشرح في
 * رسالة الخطأ، بدل أن يبقى الحاجز خفيًّا.
 */
class UploadLimits
{
    /** يحوّل «128M» أو «2G» أو «512K» أو «-1» إلى بايتات. صفر تعني «بلا حدّ» */
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** صياغة مقروءة: 134217728 ⇒ «128 ميغابايت» */
    public static function human(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1).' غيغابايت';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024)).' ميغابايت';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' كيلوبايت';
        }

        return $bytes.' بايت';
    }

    /** حدّ الطلب الواحد. صفر تعني «بلا حدّ» */
    public static function postMaxBytes(): int
    {
        return self::toBytes((string) ini_get('post_max_size'));
    }

    /** حدّ الملف الواحد */
    public static function uploadMaxBytes(): int
    {
        return self::toBytes((string) ini_get('upload_max_filesize'));
    }

    /**
     * الحدّ الفعلي الذي يشعر به المالك: أصغر الحدّين.
     *
     * الملف يخضع لحدّين معًا — حدّه الخاص `upload_max_filesize`، وحدّ الطلب
     * `post_max_size` الذي يحمل معه بقية الحقول. وأصغرهما هو ما يقف حاجزًا
     * فعلًا. وعرض حدّ الطلب وحده يُربك: يقول للمالك «136 ميغابايت» بينما
     * الحقل يرفض ما فوق 128.
     */
    public static function effectiveBytes(): int
    {
        $file = self::uploadMaxBytes();
        $post = self::postMaxBytes();

        if ($file <= 0) {
            return $post;
        }

        if ($post <= 0) {
            return $file;
        }

        return min($file, $post);
    }

    /** هل الحدّ الفعلي أقل من الحد الذي نطلبه؟ */
    public static function isBelow(int $wantedBytes): bool
    {
        $limit = self::effectiveBytes();

        return $limit > 0 && $limit < $wantedBytes;
    }

    /** نصّ مختصر للحدّ الحالي — يُعرض في اللوحة */
    public static function summary(): string
    {
        return 'الملف '.self::human(self::uploadMaxBytes())
            .' · الطلب '.self::human(self::postMaxBytes());
    }
}
