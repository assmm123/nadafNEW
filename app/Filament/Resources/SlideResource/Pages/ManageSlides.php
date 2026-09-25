<?php

namespace App\Filament\Resources\SlideResource\Pages;

use App\Filament\Resources\SlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlides extends ManageRecords
{
    protected static string $resource = SlideResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('شريحة جديدة')];
    }
}
