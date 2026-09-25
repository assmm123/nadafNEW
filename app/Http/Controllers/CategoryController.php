<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->active()->firstOrFail();

        $categoryIds = $category->children->pluck('id')->push($category->id)->all();

        $query = Product::active()
            ->whereIn('category_id', $categoryIds)
            ->with(['media', 'variants']);

        // فلترة اللون والمقاس
        if ($color = $request->query('color')) {
            $query->whereHas('variants', fn ($q) => $q->where('color', $color)->where('quantity', '>', 0));
        }
        if ($size = $request->query('size')) {
            $query->whereHas('variants', fn ($q) => $q->where('size', $size)->where('quantity', '>', 0));
        }

        // فلترة السعر بالدولار
        if ($min = $request->query('min_price')) {
            $query->where('price_usd', '>=', (float) $min);
        }
        if ($max = $request->query('max_price')) {
            $query->where('price_usd', '<=', (float) $max);
        }

        // الترتيب
        match ($request->query('sort', 'latest')) {
            'price_asc' => $query->orderBy('price_usd'),
            'price_desc' => $query->orderByDesc('price_usd'),
            // ترتيب بمجموع فرعي مرتبط: يعمل على SQLite وMySQL معًا، بخلاف
            // leftJoin + selectRaw('products.*') + groupBy الذي يكسر الاستعلام
            // على MySQL عند تفعيل ONLY_FULL_GROUP_BY (وهو إعداد الاستضافة)،
            // كما أنه لا يكرّر صفوف المنتجات فلا يفسد paginate.
            'bestselling' => $query->orderByDesc(
                OrderItem::selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('order_items.product_id', 'products.id')
            ),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        // خيارات الفلاتر المتاحة ضمن القسم
        $variantsQuery = ProductVariant::whereHas('product', function ($q) use ($categoryIds) {
            $q->active()->whereIn('category_id', $categoryIds);
        });

        return view('category', [
            'category' => $category,
            'products' => $products,
            'colorOptions' => (clone $variantsQuery)->whereNotNull('color')->distinct()->pluck('color'),
            'sizeOptions' => (clone $variantsQuery)->whereNotNull('size')->distinct()->pluck('size'),
        ]);
    }
}
