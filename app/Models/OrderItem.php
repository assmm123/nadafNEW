<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'name_ar',
        'name_en',
        'color',
        'size',
        'quantity',
        'unit_price_usd',
        'unit_cost_usd',
        'total_price_usd',
        'is_wholesale',
    ];

    protected $casts = [
        'is_wholesale' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function displayName(): string
    {
        $name = app()->getLocale() === 'en'
            ? ($this->name_en ?: $this->name_ar)
            : $this->name_ar;

        $variant = trim(implode(' / ', array_filter([$this->color, $this->size])));

        return $variant ? "$name ($variant)" : $name;
    }
}
