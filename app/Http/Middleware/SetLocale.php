<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\CommunicationMethod;
use App\Models\Page;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class SetLocale
{
    public function handle(Request $request, \Closure $next)
    {
        // المستخدم المحجوب يُسجَّل خروجه تلقائيًا
        if (Auth::check() && Auth::user()->isBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerate();
            return redirect()->route('home')->with('error', __('auth.blocked'));
        }

        $locale = session('locale', config('app.locale'));
        app()->setLocale(in_array($locale, ['ar', 'en']) ? $locale : 'ar');

        if (! session('currency')) {
            session(['currency' => 'usd']);
        }

        View::share([
            'headerCategories' => Category::active()->whereNull('parent_id')->orderBy('sort_order')->get(),
            'contactMethods' => CommunicationMethod::active()->orderBy('sort_order')->get(),
            'footerPages' => Page::active()->get(),
            'cartCount' => CartService::count(),
        ]);

        return $next($request);
    }
}
