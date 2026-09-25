<?php

namespace App\Filament\Resources\ChatQuestionResource\Pages;

use App\Filament\Resources\ChatQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatQuestions extends ListRecords
{
    protected static string $resource = ChatQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('سؤال جديد'),
        ];
    }
}
