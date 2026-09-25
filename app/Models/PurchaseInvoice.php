<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'code',
        'supplier_id',
        'status',
        'total_cost',
        'notes',
        'created_by',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * توليد كود فاتورة فريد PI-XXXX.
     *
     * لا يكفي max('id') + 1 وحده: حذف أحدث فاتورة مسودة يجعل الكود التالي
     * يعيد استخدام كود سبق أن وُجد، فيصطدم القيد الفريد على عمود code ويفشل
     * الحفظ بخطأ قاعدة بيانات. الحلقة تضمن كودًا حرًّا فعليًا (نفس نمط Order::generateCode).
     */
    public static function generateCode(): string
    {
        $next = (int) (static::max('id') ?: 0) + 1;

        do {
            $code = 'PI-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === 'draft' && $this->items()->exists();
    }
}
