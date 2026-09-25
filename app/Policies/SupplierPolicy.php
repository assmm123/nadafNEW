<?php

namespace App\Policies;

class SupplierPolicy extends StaffPolicy
{
    protected string $resource = 'purchasing';
}
