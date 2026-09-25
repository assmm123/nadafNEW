<?php

namespace App\Filament\Resources\ChatQuestionResource\Pages;

use App\Filament\Resources\ChatQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateChatQuestion extends CreateRecord
{
    protected static string $resource = ChatQuestionResource::class;
}
