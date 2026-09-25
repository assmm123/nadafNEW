<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * الأقسام — تبويبات تُريح القائمة من الأقسام التي لا تهمّ الآن.
 *
 * وأهمّها تبويب «فارغة»: القسم الفارغ لا يبيع شيئًا، والمالك لا يكتشفه إلا
 * بالصدفة وسط قائمة مختلطة. فصار له تبويب بعدّاده.
 */
class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $count = fn (callable $filter) => Category::query()->tap($filter)->count();

        $tab = fn (string $label, callable $filter) => Tab::make($label)
            ->badge($count($filter))
            ->modifyQueryUsing(fn (Builder $query) => $filter($query));

        return [
            'all' => $tab('الكل', fn ($q) => $q),

            'main' => $tab('رئيسية', fn ($q) => $q->whereNull('parent_id')),

            'sub' => $tab('فرعية', fn ($q) => $q->whereNotNull('parent_id')),

            'with_products' => $tab('فيها منتجات', fn ($q) => $q->whereHas('products')),

            'empty' => $tab('فارغة', fn ($q) => $q->whereDoesntHave('products')),

            'hidden' => $tab('مخفية', fn ($q) => $q->where('is_active', false)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('＋ قسم جديد'),
        ];
    }
}
