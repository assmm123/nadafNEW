<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * خمس حالات فقط — حُذفت «مؤكد» لأنها لا لحظة تمرّ فيها:
     * القبض يصير علامة مستقلة (الختم الأخضر) لا حالة.
     * و«تم الشحن» صارت info بدل secondary — الرمادي يعني «معطّل» والشحن نشط.
     */
    public const STATUSES = [
        'pending' => ['ar' => 'قيد المراجعة', 'en' => 'Pending', 'color' => 'warning'],
        'preparing' => ['ar' => 'قيد التحضير', 'en' => 'Preparing', 'color' => 'primary'],
        'shipped' => ['ar' => 'تم الشحن', 'en' => 'Shipped', 'color' => 'info'],
        'delivered' => ['ar' => 'تم التسليم', 'en' => 'Delivered', 'color' => 'success'],
        'cancelled' => ['ar' => 'ملغي', 'en' => 'Cancelled', 'color' => 'danger'],
    ];

    protected $fillable = [
        'user_id',
        // بيانات الضيف: تُملأ حين يُتمّ الطلب بلا حساب
        'customer_name',
        'customer_phone',
        'order_code',
        'status',
        'stamped_at',
        'stamped_by',
        'subtotal_usd',
        'discount_usd',
        'shipping_usd',
        'total_usd',
        'exchange_rate',
        'total_syp',
        'shipping_method',
        'shipping_address',
        'city',
        'payment_method_id',
        'payment_proof_path',
        'payment_reference',
        'payment_sender_name',
        'payment_confirmed_at',
        'payment_confirmed_by',
        'archived_at',
        'coupon_id',
        'notes',
    ];

    protected $casts = [
        'stamped_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * ═══ بيانات صاحب الطلب — مصدر واحد ═══
     *
     * الطلب قد يكون لحساب مسجَّل أو لضيف بلا حساب (إتمام الطلب صار متاحًا
     * بلا تسجيل). فكل من يقرأ اسم العميل أو رقمه يمرّ من هنا، ولا يقرأ
     * `user->name` مباشرة — وإلا انكسر على طلبات الضيوف.
     */
    public function customerName(): string
    {
        return $this->user?->name ?: ($this->customer_name ?: '—');
    }

    public function customerPhone(): ?string
    {
        return $this->user?->phone ?: $this->customer_phone;
    }

    public function customerEmail(): ?string
    {
        return $this->user?->email;
    }

    /** طلب ضيف: لا حساب مرتبط به */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public static function statusLabel(string $status): string
    {
        $s = static::STATUSES[$status] ?? null;

        return $s ? $s[app()->getLocale()] : $status;
    }

    public static function statusColor(string $status): string
    {
        return static::STATUSES[$status]['color'] ?? 'secondary';
    }

    public function statusLabelAttribute(): string
    {
        return static::statusLabel($this->status);
    }

    /** توليد كود طلب فريد NDF-XXXXXX */
    public static function generateCode(): string
    {
        do {
            $code = 'NDF-'.strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('order_code', $code)->exists());

        return $code;
    }

    public function stampedBy()
    {
        return $this->belongsTo(User::class, 'stamped_by');
    }

    public function paymentConfirmedBy()
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function isWholesale(): bool
    {
        return $this->items()->where('is_wholesale', true)->exists();
    }

    /** هل قُبض المال؟ — الختم الأخضر */
    public function isPaid(): bool
    {
        return $this->payment_confirmed_at !== null;
    }

    /** هل سُلِّم الطلب؟ — الختم الذهبي */
    public function isDelivered(): bool
    {
        return $this->stamped_at !== null;
    }

    /**
     * أي ختم ثُبّت على الطلب؟
     *   gold  = الختم الذهبي (سُلِّم)   green = الختم الأخضر (قُبض المال)   none = لا شيء
     * التسليم يتقدّم على القبض لأنه يُثبَّت بعده دائمًا، فالأعلى هو الحالة الفعلية.
     */
    public function stampState(): string
    {
        return $this->isDelivered() ? 'gold' : ($this->isPaid() ? 'green' : 'none');
    }

    /**
     * مرحلة اشتراط قبض المال — من الإعدادات:
     * on_confirm | on_ship | on_deliver | optional
     */
    public static function collectStage(): string
    {
        return (string) Setting::get('payment_collect_stage', 'on_deliver');
    }

    /** هل يُشترط القبض قبل التسليم؟ (خيار «غير مشروط» يسمح بالتسليم بلا قبض) */
    public function requiresPaymentBeforeDelivery(): bool
    {
        return static::collectStage() !== 'optional';
    }

    /** هل يمكن تسليمه؟ — غير مسلَّم من قبل، وليس قيد المراجعة ولا ملغيًا */
    public function canBeDelivered(): bool
    {
        return $this->stamped_at === null
            && ! in_array($this->status, ['pending', 'cancelled'], true);
    }

    /** هل يمكن إلغاؤه؟ — لا يُلغى مسلَّم ولا ملغي */
    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['delivered', 'cancelled'], true);
    }

    /** وصف مختصر لحالة القبض للعرض */
    public function paymentStateLabel(): string
    {
        return $this->isPaid() ? 'مقبوض' : 'غير مقبوض';
    }
}
