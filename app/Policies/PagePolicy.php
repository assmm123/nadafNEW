<?php

namespace App\Policies;

class PagePolicy extends StaffPolicy
{
    protected string $resource = 'content';
}
