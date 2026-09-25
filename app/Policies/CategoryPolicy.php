<?php

namespace App\Policies;

class CategoryPolicy extends StaffPolicy
{
    protected string $resource = 'catalog';
}
