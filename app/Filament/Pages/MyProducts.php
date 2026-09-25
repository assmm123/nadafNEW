<?php

namespace App\Filament\Pages;

use App\Models\Category;
use Filament\Pages\Page;

/**
 * «منتجاتي» — بوابة كروت متساوية بالأقسام، والنقر يعرض منتجات القسم وحده ككروت.
 * الجرد الكلي (جدول المنتجات الكامل المفلتر) يبقى رابطاً احتياطياً أسفل البوابة.
 */
class MyProducts extends Page
{
    /** عرض المنتجات — متاح لمسؤول المخزون أيضًا (قراءة) */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('products.view');
    }
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.my-products';

    /**
     * القسم المفتوح — **بلا `#[Url]` عمدًا**.
     *
     * جرّبت وضعه في الرابط فتبيّن أنه يضرّ: أربعة أقسام من سبعة فارغة، فمجرد
     * فتح أحدها يجعل `?cat=` يبقى في الرابط، وكل تحديث للصفحة يعيد المالك إلى
     * قسم فارغ فيظنّ القسم كله خالٍ. والبوابة بالكروت تُفتح في كل مرة بلا هذا
     * الالتباس، والعودة بنقرة واحدة.
     */
    public ?int $openCategoryId = null;

    /** المنتج الذي فُتحت تفاصيله داخل الكرت (بلا انتقال لصفحة) */
    public ?int $openProductId = null;

    public static function getNavigationLabel(): string
    {
        return 'منتجاتي';
    }

    public function getTitle(): string
    {
        return $this->openCategoryId ? 'منتجاتي — عرض معزول' : 'منتجاتي';
    }

    public function openCategory(int $id): void
    {
        $this->openCategoryId = $id;
        $this->openProductId = null;
    }

    public function closeCategory(): void
    {
        $this->openCategoryId = null;
        $this->openProductId = null;
    }

    /** توسيع تفاصيل منتج أو طيّها — في مكانه بلا مغادرة الصفحة */
    public function toggleDetails(int $id): void
    {
        $this->openProductId = $this->openProductId === $id ? null : $id;
    }

    protected function getViewData(): array
    {
        $categories = Category::with(['products.variants'])->withCount('products')->orderBy('sort_order')->get()
            ->map(fn (Category $cat) => [
                'id' => $cat->id,
                'name' => $cat->name_ar,
                'image' => $cat->imageUrl(),
                'count' => $cat->products->count(),
                // «نافد» من نفس مصدر الحقيقة الذي يعرضه المتجر
                'out' => $cat->products->filter(fn ($p) => $p->stockInfo()['kind'] === 'out')->count(),
                'slug' => $cat->slug,
            ])->values();

        $openCategory = null;
        $openProducts = collect();

        if ($this->openCategoryId) {
            $cat = Category::with(['products.variants', 'products.media'])->find($this->openCategoryId);

            if ($cat) {
                $openCategory = $categories->firstWhere('id', $cat->id);

                $openProducts = $cat->products->map(fn ($p) => [
                    'model' => $p,
                    'image' => $p->imageUrl(),
                    'stock' => $p->stockInfo(),
                    'skus' => $p->variants->pluck('sku')->filter()->implode(' · '),
                    // شرائح الألوان — كل لون مرة واحدة، بدرجته الحقيقية
                    'colors' => $p->variants
                        ->filter(fn ($v) => filled($v->color))
                        ->unique('color')
                        ->map(fn ($v) => ['hex' => $v->color_hex ?: '#888888', 'label' => $v->color])
                        ->values(),
                    'sizes' => $p->variants->pluck('size')->filter()->unique()->values(),
                ]);
            }
        }

        return [
            'categories' => $categories,
            'openCategory' => $openCategory,
            'openProducts' => $openProducts,
            'totalProducts' => \App\Models\Product::count(),
        ];
    }
}
