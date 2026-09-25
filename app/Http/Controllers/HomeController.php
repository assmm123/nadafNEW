<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Slide;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'slides' => Slide::active()->ordered()->get(),
            'categories' => Category::active()->whereNull('parent_id')->ordered()
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->get(),
            'featured' => Product::active()->featured()->with(['media', 'variants'])->latest()->take(8)->get(),
            'latest' => Product::active()->with(['media', 'variants'])->latest()->take(8)->get(),
        ]);
    }
}
