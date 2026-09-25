<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $products = Product::active()
            ->with('media')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%")
                        ->orWhere('description_ar', 'like', "%{$q}%")
                        ->orWhere('description_en', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('search', [
            'q' => $q,
            'products' => $products,
        ]);
    }
}
