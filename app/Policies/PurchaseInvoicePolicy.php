<?php

namespace App\Policies;

class PurchaseInvoicePolicy extends StaffPolicy
{
    protected string $resource = 'purchasing';
}
