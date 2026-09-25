<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * المالك يتجاوز كل السياسات.
         *
         * مع حماية من قفل المتجر على نفسه: لا يُسمح بحذف حساب مالك — لا من
         * مالك آخر ولا من صاحبه. (بلا هذا الشرط يستطيع المالك حذف حسابه
         * فيبقى المتجر بلا من يديره، ولا سبيل للاستعادة من الواجهة.)
         * التعديل يبقى مسموحًا كي يستطيع تحديث بياناته.
         *
         * إرجاع null (لا false) مهم: يترك الفحص يكمل عبر السياسات لباقي الأدوار.
         */
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->isOwner()) {
                return null;
            }

            $target = $arguments[0] ?? null;

            if ($target instanceof User && $target->isOwner() && $ability === 'delete') {
                return false;
            }

            return true;
        });

        /**
         * كشف التحميل الكسول (N+1) في بيئة التطوير.
         *
         * نُسجّل تحذيرًا بدل رمي استثناء: رميه يوقف الصفحة كلها عند أول
         * علاقة غير محمّلة، فيتحوّل التطوير إلى مطاردة أخطاء 500 بدل إصلاح
         * استعلام. التحذير يظهر في storage/logs/laravel.log مع اسم الموديل
         * والعلاقة، فيكفي grep على «Lazy loading» لمعرفة موضع المشكلة.
         */
        Model::preventLazyLoading(! app()->isProduction());

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            logger()->warning('Lazy loading detected', [
                'model' => $model::class,
                'relation' => $relation,
            ]);
        });
    }
}
