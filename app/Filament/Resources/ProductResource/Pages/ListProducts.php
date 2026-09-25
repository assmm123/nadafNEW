<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('منتج جديد'),
        ];
    }

    /** فتح الصفحة بفلتر قسم محدد مسبقًا: ?category=ID (يأتي من جدول «الأقسام») */
    public function mount(): void
    {
        parent::mount();

        if ($categoryId = request('category')) {
            $this->tableFilters['category']['value'] = (int) $categoryId;
        }
    }
}
