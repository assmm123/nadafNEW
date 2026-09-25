<?php

namespace App\Policies;

class ChatQuestionPolicy extends StaffPolicy
{
    protected string $resource = 'chat';
}
