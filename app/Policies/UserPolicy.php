<?php

namespace App\Policies;

use App\Models\User;

/**
 * سياسة المستخدمين — الأكثر حساسية لأنها تحمي حساب المالك.
 *
 * القاعدة الحرجة: أي موظف يملك صلاحية إدارة المستخدمين لا يستطيع تعديل حساب
 * المالك ولا حذفه. بدونها يستطيع «مدير متجر» تغيير كلمة مرور المالك أو حظره
 * فيُقفل المتجر على صاحبه.
 *
 * (المالك نفسه يتجاوز هذا الملف عبر Gate::before، مع منع صريح لحذف حساب مالك.)
 */
class UserPolicy extends StaffPolicy
{
    protected string $resource = 'users';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, mixed $model = null): bool
    {
        // كل موظف يرى حسابه، وإدارة المستخدمين ترى الجميع
        return $user->hasPermission('users.view')
            || ($model instanceof User && $user->id === $model->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, mixed $model = null): bool
    {
        if (! $model instanceof User) {
            return false;
        }

        // حماية المالك: لا يعدّله غيره — ولو ملك صلاحية إدارة المستخدمين
        if ($model->isOwner()) {
            return $user->id === $model->id;
        }

        // كل موظف يعدّل حسابه
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermission('users.update');
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if (! $model instanceof User) {
            return false;
        }

        // لا حذف لحساب المالك، ولا حذف للنفس
        if ($model->isOwner() || $user->id === $model->id) {
            return false;
        }

        return $user->hasPermission('users.delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
