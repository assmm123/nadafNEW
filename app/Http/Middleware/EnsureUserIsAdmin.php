<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * بوابة دخول لوحة التحكم.
 *
 * الاسم قديم لكن المعنى أوسع الآن: يسمح لكل أدوار الطاقم (مالك · مدير متجر ·
 * مسؤول مخزون · دعم)، لأن التمييز بينهم يجري داخل اللوحة عبر السياسات
 * (App\Policies) لا هنا. هذا الحارس يمنع العملاء والمحظورين فقط.
 *
 * مسجَّل كـ persistent في AdminPanelProvider، فيُطبَّق على نداءات Livewire أيضًا.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, \Closure $next)
    {
        $user = $request->user();

        abort_unless($user && $user->isAdmin() && ! $user->isBlocked(), 403);

        return $next($request);
    }
}
