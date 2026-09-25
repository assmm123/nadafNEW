<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'notes'];

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class);
    }
}
