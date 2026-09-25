<?php

namespace App\Filament\Resources\ChatQuestionResource\Pages;

use App\Filament\Resources\ChatQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatQuestion extends EditRecord
{
    protected static string $resource = ChatQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
