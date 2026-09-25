<?php

namespace App\Policies;

class StockMovementPolicy extends StaffPolicy
{
    protected string $resource = 'stock';
}
