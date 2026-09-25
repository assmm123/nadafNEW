<?php

namespace App\Policies;

class OrderPolicy extends StaffPolicy
{
    protected string $resource = 'orders';
}
