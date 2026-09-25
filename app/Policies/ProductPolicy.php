<?php

namespace App\Policies;

class ProductPolicy extends StaffPolicy
{
    protected string $resource = 'products';
}
