<?php

namespace App\Policies;

use App\Models\User;

/**
 * أساس سياسات الطاقم.
 *
 * كل سياسة تحدّد بادئة موردها (مثال: orders)، وهذا الأب يترجم أفعال Laravel
 * القياسية إلى صلاحيات بالصيغة "المورد.الفعل". النتيجة: السياسات الفعلية
 * قصيرة وواضحة، والمصفوفة كلها في مكان واحد (App\Support\StaffPermissions).
 *
 * المالك لا يمر من هنا — يتجاوزه Gate::before في AppServiceProvider.
 */
abstract class StaffPolicy
{
    /** بادئة الصلاحية لهذا المورد: orders · products · stock ... */
    protected string $resource;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission("{$this->resource}.view");
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $user->hasPermission("{$this->resource}.view");
    }

    public function create(User $user): bool
    {
        return $user->hasPermission("{$this->resource}.create");
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasPermission("{$this->resource}.update");
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->hasPermission("{$this->resource}.delete");
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission("{$this->resource}.delete");
    }

    /**
     * إعادة الترتيب بالسحب — فعل تعديل لا صلاحية مستقلة.
     * بدونه يرفض Filament إظهار مقابض السحب في كل الجداول المرتَّبة.
     */
    public function reorder(User $user): bool
    {
        return $user->hasPermission("{$this->resource}.update");
    }

    /** لا حذف نهائي من الواجهة — الأرشيف هو المسار المعتمد */
    public function forceDelete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return false;
    }
}
