<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session(['locale' => $locale]);
    }

    return back();
})->name('lang.switch');

Route::get('/currency/{code}', function (string $code) {
    if (in_array($code, ['usd', 'syp'])) {
        session(['currency' => $code]);
    }

    return back();
})->name('currency.switch');

Route::get('/c/{slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/p/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page.show');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    // throttle: حد 6 محاولات دخول لكل دقيقة لكل IP — يمنع تخمين كلمات المرور
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.store');

    // استعادة كلمة المرور — throttle يمنع إغراق بريد العميل بالرسائل
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ═══ الدفع — بلا تسجيل دخول ═══
// كان داخل مجموعة `auth`، فالعميل الذي لا يريد إنشاء حساب **لا يستطيع الشراء
// إطلاقًا**. وتسجيل الدخول صار اختياريًّا: من يسجّل يجد طلباته في حسابه،
// ومن لا يسجّل يُدخل اسمه ورقمه وعنوانه ويُتمّ الطلب.
// (وصفحة النجاح محميّة أدناه: بحساب صاحب الطلب، أو برمز الطلب في جلسته.)
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::get('/checkout/success/{code}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'profile'])->name('account.profile');
    Route::post('/account', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/account/orders/{code}', [AccountController::class, 'order'])->name('account.order');
    Route::get('/account/orders/{code}/invoice', [AccountController::class, 'invoice'])->name('account.invoice');
});

// فاتورة الطلب — للأدمن (طباعة)
Route::get('/admin/orders/{order}/invoice', [InvoiceController::class, 'show'])
    ->middleware(['auth', 'admin'])
    ->name('admin.invoice');

// تصدير تقارير المبيعات والأرباح — للأدمن
Route::get('/admin/reports/export', [ReportExportController::class, 'export'])
    ->middleware(['auth', 'admin'])
    ->name('admin.reports.export');
