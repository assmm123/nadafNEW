<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->with(['category', 'media', 'variants'])
            ->firstOrFail();

        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('media')
            ->latest()
            ->take(4)
            ->get();

        return view('product', [
            'product' => $product,
            'related' => $related,
        ]);
    }
}
