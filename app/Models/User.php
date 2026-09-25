<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Support\StaffPermissions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * هل يدخل لوحة التحكم؟
     *
     * ملاحظة: صار يشمل كل أدوار الطاقم (مالك/مدير متجر/مسؤول مخزون/دعم)،
     * لا `role === 'admin'` فقط — لأن كل هؤلاء يحتاجون دخول اللوحة بصلاحيات
     * محدودة تحدّدها السياسات. الواجهة تستخدم هذه الدالة لعرض رابط اللوحة،
     * فهي الآن تُظهره لكل موظف بشكل صحيح. للتحقق من المالك استخدم isOwner().
     */
    public function isAdmin(): bool
    {
        return StaffPermissions::isStaff($this->role);
    }

    /** الدور بعد توحيد القيمة القديمة admin ← owner */
    public function staffRole(): ?string
    {
        return StaffPermissions::normalise($this->role);
    }

    /**
     * هل يدخل لوحة التحكم؟ — عقد Filament الإلزامي.
     *
     * بدونه لا يستخدم Filament هذه الدالة أصلًا، بل يسقط إلى فحص البيئة:
     *   abort_if($user instanceof FilamentUser ? ... : config('app.env') !== 'local', 403);
     * أي أنه **يسمح في `local` فقط ويرفض في كل بيئة أخرى**.
     * فكانت اللوحة تعمل على جهاز التطوير وحده، وتُغلق كليًا على الإنتاج —
     * حتى على المالك. هذا العقد هو ما يجعلها تعمل في كل البيئات.
     *
     * والشرط نفسه الموجود في EnsureUserIsAdmin (دفاع بطبقتين).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->isBlocked();
    }

    /** المالك — كل شيء بلا استثناء */
    public function isOwner(): bool
    {
        return $this->staffRole() === StaffPermissions::ROLE_OWNER;
    }

    /** هل يملك صلاحية محددة؟ مثال: $user->hasPermission('orders.update') */
    public function hasPermission(string $permission): bool
    {
        return StaffPermissions::allows($this->role, $permission);
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /** إشعار استعادة كلمة المرور بالعربية بدل نص Laravel الإنجليزي */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
