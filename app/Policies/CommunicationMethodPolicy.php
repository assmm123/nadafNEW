<?php

namespace App\Policies;

class CommunicationMethodPolicy extends StaffPolicy
{
    protected string $resource = 'payments';
}
