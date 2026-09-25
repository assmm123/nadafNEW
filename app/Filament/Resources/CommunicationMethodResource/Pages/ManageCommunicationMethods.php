<?php

namespace App\Filament\Resources\CommunicationMethodResource\Pages;

use App\Filament\Resources\CommunicationMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCommunicationMethods extends ManageRecords
{
    protected static string $resource = CommunicationMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('وسيلة جديدة')];
    }
}
