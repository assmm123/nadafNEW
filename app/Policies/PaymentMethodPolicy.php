<?php

namespace App\Policies;

class PaymentMethodPolicy extends StaffPolicy
{
    protected string $resource = 'payments';
}
