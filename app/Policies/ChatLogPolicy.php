<?php

namespace App\Policies;

class ChatLogPolicy extends StaffPolicy
{
    protected string $resource = 'chat';
}
