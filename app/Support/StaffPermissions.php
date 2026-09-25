<?php

namespace App\Support;

/**
 * مصدر الحقيقة الوحيد لأدوار الطاقم وصلاحياتهم.
 *
 * القاعدة: الصلاحية سلسلة بصيغة "المورد.الفعل" مثل orders.update.
 * والأدوار تُمنح إما صلاحية محددة، أو بادئة كاملة (`stock.*`)، أو `*` للمالك.
 *
 * المالك يتجاوز هذه المصفوفة كليًا عبر Gate::before في AppServiceProvider،
 * لكنه يبقى مذكورًا هنا بـ `*` حتى تعمل الفحوص المباشرة (User::hasPermission)
 * بلا استثناءات خاصة في كل موضع.
 *
 * ملاحظة توافق: القيمة القديمة `admin` في عمود users.role تعني المالك،
 * ويُترجمها normalise() تلقائيًا فلا حاجة لتعديل بيانات قائمة.
 */
final class StaffPermissions
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_WAREHOUSE = 'warehouse';
    public const ROLE_SUPPORT = 'support';
    public const ROLE_CUSTOMER = 'customer';

    /** قيمة قديمة في قاعدة البيانات — تُعامل كمالك */
    public const LEGACY_OWNER = 'admin';

    /** الأدوار التي تدخل لوحة التحكم (العميل ليس منها) */
    public const ROLES = [
        self::ROLE_OWNER => 'المالك',
        self::ROLE_MANAGER => 'مدير متجر',
        self::ROLE_WAREHOUSE => 'مسؤول مخزون',
        self::ROLE_SUPPORT => 'دعم العملاء',
    ];

    /** وصف مختصر يظهر تحت كل دور في قائمة الاختيار */
    public const DESCRIPTIONS = [
        self::ROLE_OWNER => 'كل شيء بلا استثناء',
        self::ROLE_MANAGER => 'الطلبات والمنتجات والمحتوى — بلا مستخدمين ولا إعدادات',
        self::ROLE_WAREHOUSE => 'المخزون والجرد والموردون — بلا أسعار ولا أرباح',
        self::ROLE_SUPPORT => 'عرض الطلبات وتغيير حالتها — بلا أسعار ولا حذف',
    ];

    /**
     * المصفوفة: الدور → الصلاحيات الممنوحة.
     * أي صلاحية غير مذكورة = ممنوعة.
     */
    private const MATRIX = [
        self::ROLE_OWNER => ['*'],

        self::ROLE_MANAGER => [
            'orders.view', 'orders.update', 'orders.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete',
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete',
            'content.view', 'content.create', 'content.update', 'content.delete',
            'payments.view', 'payments.create', 'payments.update', 'payments.delete',
            'stock.view', 'stock.create', 'stock.update', 'stock.adjust',
            'purchasing.view', 'purchasing.create', 'purchasing.update', 'purchasing.delete',
            'reports.view',
            'chat.view', 'chat.update',
        ],

        self::ROLE_WAREHOUSE => [
            'products.view',
            'stock.view', 'stock.create', 'stock.update', 'stock.adjust',
            'purchasing.view', 'purchasing.create', 'purchasing.update', 'purchasing.delete',
        ],

        self::ROLE_SUPPORT => [
            'orders.view', 'orders.update',
            'chat.view', 'chat.update',
        ],
    ];

    /**
     * كل الصلاحيات المعروفة مع تسميتها العربية.
     * تُستخدم لبناء جدول مرجعي (أمر roles:matrix) وأي واجهة تعرض المصفوفة،
     * ولها قيمة ثالثة: تمنع ظهور صلاحية مطبوعة بالخطأ في MATRIX بلا تعريف.
     */
    public const PERMISSIONS = [
        'orders.view' => 'عرض الطلبات',
        'orders.update' => 'تغيير حالة الطلب',
        'orders.delete' => 'إلغاء أو حذف طلب',

        'products.view' => 'عرض المنتجات',
        'products.create' => 'إضافة منتج',
        'products.update' => 'تعديل منتج أو سعر',
        'products.delete' => 'حذف منتج',

        'catalog.view' => 'عرض الأقسام',
        'catalog.create' => 'إضافة قسم',
        'catalog.update' => 'تعديل قسم',
        'catalog.delete' => 'حذف قسم',

        'coupons.view' => 'عرض الكوبونات',
        'coupons.create' => 'إضافة كوبون',
        'coupons.update' => 'تعديل كوبون',
        'coupons.delete' => 'حذف كوبون',

        'content.view' => 'عرض السلايدر والصفحات',
        'content.create' => 'إضافة سلايد أو صفحة',
        'content.update' => 'تعديل سلايد أو صفحة',
        'content.delete' => 'حذف سلايد أو صفحة',

        'payments.view' => 'عرض وسائل الدفع والتواصل',
        'payments.create' => 'إضافة وسيلة دفع أو تواصل',
        'payments.update' => 'تعديل وسيلة دفع أو تواصل',
        'payments.delete' => 'حذف وسيلة دفع أو تواصل',

        'stock.view' => 'عرض حركات المخزون',
        'stock.create' => 'تسجيل حركة مخزون',
        'stock.update' => 'تعديل حركة مخزون',
        'stock.adjust' => 'تسوية المخزون والجرد الفعلي',

        'purchasing.view' => 'عرض فواتير الشراء والموردين',
        'purchasing.create' => 'إضافة فاتورة شراء أو مورد',
        'purchasing.update' => 'تعديل فاتورة شراء أو مورد',
        'purchasing.delete' => 'حذف فاتورة شراء أو مورد',

        'reports.view' => 'الأرباح والتقارير والتصدير',

        'chat.view' => 'عرض الدردشة وأسئلتها',
        'chat.update' => 'الرد على الدردشة وتعديل الأسئلة',

        'users.view' => 'عرض المستخدمين',
        'users.create' => 'إضافة مستخدم',
        'users.update' => 'تعديل مستخدم أو دوره',
        'users.delete' => 'حذف مستخدم',

        'settings.manage' => 'الإعدادات العامة وبوت تيليجرام',
    ];

    /**
     * المصفوفة كاملة كجدول: [الصلاحية => [الدور => bool]].
     * مصدرها PERMISSIONS × ROLES عبر allows() — فلا تتعارض مع منطق التنفيذ.
     */
    public static function matrix(): array
    {
        $rows = [];

        foreach (array_keys(self::PERMISSIONS) as $permission) {
            foreach (array_keys(self::ROLES) as $role) {
                $rows[$permission][$role] = self::allows($role, $permission);
            }
        }

        return $rows;
    }

    /** توحيد القيم القديمة إلى أسماء الأدوار الجديدة */
    public static function normalise(?string $role): ?string
    {
        return $role === self::LEGACY_OWNER ? self::ROLE_OWNER : $role;
    }

    /** هل هذا الدور يدخل لوحة التحكم؟ */
    public static function isStaff(?string $role): bool
    {
        return array_key_exists((string) self::normalise($role), self::ROLES);
    }

    /** اسم الدور للعرض (شارة الجدول مثلًا) */
    public static function label(?string $role): string
    {
        $role = self::normalise($role);

        return self::ROLES[$role] ?? 'عميل';
    }

    /** وصف الدور للعرض في قائمة الاختيار */
    public static function description(?string $role): string
    {
        return self::DESCRIPTIONS[self::normalise($role) ?? ''] ?? 'واجهة المتجر فقط';
    }

    /** خيارات قائمة الدور: الطاقم + العميل */
    public static function options(): array
    {
        return self::ROLES + [self::ROLE_CUSTOMER => 'عميل'];
    }

    /**
     * هل يمنح هذا الدور الصلاحية المطلوبة؟
     * يدعم المطابقة الدقيقة، والبادئة `resource.*`، والنجمة الكاملة `*`.
     */
    public static function allows(?string $role, string $permission): bool
    {
        $grants = self::MATRIX[self::normalise($role) ?? ''] ?? [];

        foreach ($grants as $grant) {
            if ($grant === '*' || $grant === $permission) {
                return true;
            }

            if (str_ends_with($grant, '.*') && str_starts_with($permission, substr($grant, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
