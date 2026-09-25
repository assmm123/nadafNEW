<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'variant_id',
        'quantity',
        'unit_cost',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
