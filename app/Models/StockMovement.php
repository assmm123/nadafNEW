<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const TYPES = [
        'sale' => ['ar' => 'بيع', 'color' => 'danger'],
        'return' => ['ar' => 'إرجاع (إلغاء)', 'color' => 'info'],
        'receive' => ['ar' => 'استلام شراء', 'color' => 'success'],
        'adjust' => ['ar' => 'تسوية جرد', 'color' => 'warning'],
        'damage' => ['ar' => 'تالف', 'color' => 'gray'],
    ];

    protected $fillable = [
        'variant_id',
        'type',
        'quantity',
        'balance_before',
        'balance_after',
        'order_id',
        'purchase_invoice_id',
        'user_id',
        'note',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function typeLabel(string $type): string
    {
        return static::TYPES[$type]['ar'] ?? $type;
    }
}
