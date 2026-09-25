<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->active()
            ->with(['sections' => fn ($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        return view('page', ['page' => $page]);
    }

    public function contact()
    {
        return view('contact');
    }
}
