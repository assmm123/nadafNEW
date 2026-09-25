<?php

namespace App\Policies;

class SlidePolicy extends StaffPolicy
{
    protected string $resource = 'content';
}
