<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * وسيلة دفع — يديرها المالك وتظهر للعميل مباشرة.
 *
 * التنظيم على طبقتين:
 *   • **مجموعة** (group): التصنيف الذي يراه العميل — التسليم باليد · المحافظ ·
 *     البنوك · التحويل المحلي · التحويل الدولي · العملات الرقمية · أخرى
 *   • **نوع** (type): الوسيلة نفسها داخل مجموعتها (شام كاش · ويسترن يونيون …)
 *
 * وكل وسيلة تتحكّم بما يراه العميل: كل حقل من حقولها يمكن إخفاؤه، ويمكن إضافة
 * حقول جديدة لها عنوان وقيمة، ويمكن كتابة شروط خاصة تظهر للعميل.
 */
class PaymentMethod extends Model
{
    /** المجموعات — التصنيف الذي يظهر للعميل */
    public const GROUPS = [
        'hand' => ['ar' => 'التسليم باليد', 'en' => 'Hand Delivery', 'icon' => 'banknote'],
        'local_wallet' => ['ar' => 'المحافظ المحلية', 'en' => 'Local Wallets', 'icon' => 'wallet'],
        'bank' => ['ar' => 'البنوك', 'en' => 'Banks', 'icon' => 'bank'],
        'local_transfer' => ['ar' => 'شركات التحويل المحلية', 'en' => 'Local Transfers', 'icon' => 'arrows-right-left'],
        'intl_transfer' => ['ar' => 'التحويل الدولي والخليجي', 'en' => 'International Transfers', 'icon' => 'globe'],
        'crypto' => ['ar' => 'العملات الرقمية', 'en' => 'Cryptocurrency', 'icon' => 'cpu-chip'],
        'other' => ['ar' => 'أخرى', 'en' => 'Other', 'icon' => 'ellipsis-horizontal'],
    ];

    /**
     * الأنواع المتاحة — كل نوع يعرف مجموعته وأيقونته.
     * والأنواع القديمة (bank · wallet · sham_cash · cash_on_delivery · other)
     * باقية كما هي فلا حاجة لتعديل بيانات قائمة.
     */
    public const TYPES = [
        // ── التسليم باليد ──
        'cash_on_delivery' => ['group' => 'hand', 'ar' => 'الدفع عند الاستلام', 'en' => 'Cash on Delivery', 'icon' => 'banknote'],
        'hand_delivery' => ['group' => 'hand', 'ar' => 'تسليم باليد', 'en' => 'Hand Delivery', 'icon' => 'hand-raised'],
        'pickup' => ['group' => 'hand', 'ar' => 'استلام من المحل', 'en' => 'Pickup', 'icon' => 'building-storefront'],

        // ── المحافظ المحلية ──
        'sham_cash' => ['group' => 'local_wallet', 'ar' => 'شام كاش', 'en' => 'Sham Cash', 'icon' => 'wallet'],
        'syriatel_cash' => ['group' => 'local_wallet', 'ar' => 'سيرياتيل كاش', 'en' => 'Syriatel Cash', 'icon' => 'wallet'],
        'mtn_cash' => ['group' => 'local_wallet', 'ar' => 'إم تي إن كاش', 'en' => 'MTN Cash', 'icon' => 'wallet'],
        'wallet' => ['group' => 'local_wallet', 'ar' => 'محفظة إلكترونية أخرى', 'en' => 'E-Wallet', 'icon' => 'card'],

        // ── البنوك ──
        'bank' => ['group' => 'bank', 'ar' => 'حوالة بنكية', 'en' => 'Bank Transfer', 'icon' => 'bank'],
        'bemo' => ['group' => 'bank', 'ar' => 'بنك بيمو', 'en' => 'BEMO Bank', 'icon' => 'bank'],
        'bank_other' => ['group' => 'bank', 'ar' => 'بنك آخر', 'en' => 'Other Bank', 'icon' => 'bank'],

        // ── شركات التحويل المحلية ──
        'alharam' => ['group' => 'local_transfer', 'ar' => 'الهرم للحوالات', 'en' => 'Al-Haram', 'icon' => 'arrows-right-left'],
        'al_omari' => ['group' => 'local_transfer', 'ar' => 'العُمري للحوالات', 'en' => 'Al-Omari', 'icon' => 'arrows-right-left'],
        'al_khaleej' => ['group' => 'local_transfer', 'ar' => 'الخليج للحوالات', 'en' => 'Al-Khaleej', 'icon' => 'arrows-right-left'],

        // ── التحويل الدولي والخليجي ──
        'western_union' => ['group' => 'intl_transfer', 'ar' => 'ويسترن يونيون', 'en' => 'Western Union', 'icon' => 'globe'],
        'moneygram' => ['group' => 'intl_transfer', 'ar' => 'موني جرام', 'en' => 'MoneyGram', 'icon' => 'globe'],
        'ria' => ['group' => 'intl_transfer', 'ar' => 'ريا', 'en' => 'Ria', 'icon' => 'globe'],
        'wise' => ['group' => 'intl_transfer', 'ar' => 'وايز', 'en' => 'Wise', 'icon' => 'globe'],
        'gulf_exchange' => ['group' => 'intl_transfer', 'ar' => 'شركة تحويل خليجية', 'en' => 'Gulf Exchange', 'icon' => 'globe'],

        // ── العملات الرقمية ──
        'crypto' => ['group' => 'crypto', 'ar' => 'عملة رقمية', 'en' => 'Cryptocurrency', 'icon' => 'cpu-chip'],
        'usdt' => ['group' => 'crypto', 'ar' => 'USDT', 'en' => 'USDT', 'icon' => 'cpu-chip'],
        'bitcoin' => ['group' => 'crypto', 'ar' => 'بيتكوين', 'en' => 'Bitcoin', 'icon' => 'cpu-chip'],

        'other' => ['group' => 'other', 'ar' => 'أخرى', 'en' => 'Other', 'icon' => 'ellipsis-horizontal'],
    ];

    /** الحقول الجاهزة التي يمكن إخفاؤها عن العميل */
    public const HIDEABLE_FIELDS = [
        'account_number' => 'رقم الحساب / المحفظة',
        'iban' => 'رقم الآيبان IBAN',
        'account_name' => 'اسم المستلم',
        'barcode' => 'صورة الباركود / QR',
        'instructions' => 'التعليمات',
        'conditions' => 'الشروط الخاصة',
    ];

    protected $fillable = [
        'type',
        'name',
        'icon_path',
        'account_number',
        'iban',
        'account_name',
        'instructions',
        'conditions',
        'min_order_usd',
        'barcode_path',
        'extra_fields',
        'hidden_fields',
        'is_active',
        'is_default',
        'requires_proof',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'requires_proof' => 'boolean',
        'extra_fields' => 'array',
        'hidden_fields' => 'array',
        'min_order_usd' => 'decimal:2',
    ];

    // ═══════════════ التصنيف ═══════════════

    public function group(): string
    {
        return static::TYPES[$this->type]['group'] ?? 'other';
    }

    public function groupLabel(): string
    {
        $group = static::GROUPS[$this->group()] ?? null;

        return $group ? $group[app()->getLocale()] : $this->group();
    }

    public function groupIcon(): string
    {
        return static::GROUPS[$this->group()]['icon'] ?? 'globe';
    }

    public function typeLabel(): string
    {
        return static::TYPES[$this->type][app()->getLocale()] ?? $this->type;
    }

    /** أيقونة النوع لمكوّن shop-icon */
    public function icon(): string
    {
        return static::TYPES[$this->type]['icon'] ?? 'globe';
    }

    /**
     * الأنواع مجمَّعة — لقائمة الاختيار في اللوحة.
     *
     * **المفتاح هو الاسم العربي للمجموعة** لا مفتاحها: Filament يرسم مفتاح
     * المصفوفة عنوانًا لـoptgroup، فلو مرّرنا `hand` لظهرت المجموعة باسم
     * «hand» للعميل. وهذا ما حدث فعلًا قبل التصحيح.
     */
    public static function groupedTypes(): array
    {
        $grouped = [];

        foreach (self::TYPES as $key => $meta) {
            $label = self::GROUPS[$meta['group']][app()->getLocale()]
                ?? self::GROUPS[$meta['group']]['ar']
                ?? $meta['group'];

            $grouped[$label][$key] = $meta['ar'];
        }

        return $grouped;
    }

    // ═══════════════ ما يراه العميل ═══════════════

    /**
     * هل يظهر هذا الحقل للعميل؟
     * الافتراض ظهور — والإخفاء استثناء صريح يحدّده المالك.
     */
    public function showsField(string $field): bool
    {
        return ! in_array($field, $this->hidden_fields ?? [], true);
    }

    /** الحقول المخصّصة المرئية فقط */
    public function visibleCustomFields(): array
    {
        return collect($this->dynamicFields())
            ->filter(fn ($f) => ($f['visible'] ?? true) !== false)
            ->values()
            ->all();
    }

    /** حقول مخصّصة يحدّدها الأدمن: [{label, type, value, visible}] */
    public function dynamicFields(): array
    {
        return collect($this->extra_fields ?? [])
            ->filter(fn ($f) => is_array($f) && ! empty($f['label']))
            ->values()
            ->all();
    }

    /** هل تصلح هذه الوسيلة لمبلغ الطلب؟ (شرط الحد الأدنى) */
    public function isAvailableFor(float $orderTotalUsd): bool
    {
        return $this->min_order_usd === null
            || (float) $this->min_order_usd <= $orderTotalUsd;
    }

    // ═══════════════ الوسائط ═══════════════

    public function barcodeUrl(): ?string
    {
        return $this->mediaUrl($this->barcode_path);
    }

    /** أيقونة الوسيلة: المرفوعة من الأدمن أولًا وإلا لا شيء (تُستخدم أيقونة النوع) */
    public function iconUrl(): ?string
    {
        return $this->mediaUrl($this->icon_path);
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'images/')
            ? asset($path)
            : \Illuminate\Support\Facades\Storage::url($path);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
