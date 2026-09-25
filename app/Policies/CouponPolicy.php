<?php

namespace App\Policies;

class CouponPolicy extends StaffPolicy
{
    protected string $resource = 'coupons';
}
