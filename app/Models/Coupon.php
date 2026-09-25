<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_order_usd',
        'expiry_date',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function isValid(?float $subtotalUsd = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        if ($this->min_order_usd && $subtotalUsd !== null && $subtotalUsd < $this->min_order_usd) {
            return false;
        }

        return true;
    }

    /** قيمة الخصم بالدولار على المجموع الفرعي */
    public function discountFor(float $subtotalUsd): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $subtotalUsd * ($this->discount_value / 100)
            : (float) $this->discount_value;

        return round(min($discount, $subtotalUsd), 2);
    }
}
