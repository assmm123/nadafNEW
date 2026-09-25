<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StaffPermissions;
use Illuminate\Console\Command;

/**
 * مرجع الأدوار والصلاحيات من سطر الأوامر.
 *
 * الغرض: مصدر واحد موثوق للتحقق من «من يملك ماذا» بلا فتح اللوحة،
 * مع إمكانية فحص مستخدم بعينه بالبريد.
 *
 * (الشاشة داخل اللوحة تحتاج ملف Blade — وهي ضمن نطاق الفرونت إند،
 * لذا المرجع هنا في الـ CLI وفي README.)
 */
class RolesMatrix extends Command
{
    protected $signature = 'roles:matrix
                            {--user= : بريد مستخدم لعرض صلاحياته الفعلية}';

    protected $description = 'عرض مصفوفة الأدوار والصلاحيات، أو صلاحيات مستخدم بعينه';

    public function handle(): int
    {
        if ($email = $this->option('user')) {
            return $this->showUser((string) $email);
        }

        $roles = array_keys(StaffPermissions::ROLES);
        $headers = ['الصلاحية', ...array_values(StaffPermissions::ROLES)];

        $rows = [];
        $matrix = StaffPermissions::matrix();

        foreach (StaffPermissions::PERMISSIONS as $permission => $label) {
            $row = [$label];

            foreach ($roles as $role) {
                $row[] = $matrix[$permission][$role] ? 'نعم' : '—';
            }

            $rows[] = $row;
        }

        $this->table($headers, $rows);

        $this->line('المالك يتجاوز المصفوفة كليًا. العميل لا يملك أي صلاحية إدارية.');
        $this->line('ملف المصفوفة: app/Support/StaffPermissions.php');

        return self::SUCCESS;
    }

    private function showUser(string $email): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("لا مستخدم بالبريد: {$email}");

            return self::FAILURE;
        }

        $role = $user->staffRole();

        $this->info("المستخدم: {$user->name} <{$user->email}>");
        $this->line('الدور: '.StaffPermissions::label($user->role)
            .($role !== $user->role ? " (القيمة في قاعدة البيانات: {$user->role})" : ''));
        $this->line('يدخل لوحة التحكم: '.($user->isAdmin() ? 'نعم' : 'لا'));
        $this->line('مالك: '.($user->isOwner() ? 'نعم' : 'لا'));
        $this->line('الحالة: '.($user->isBlocked() ? 'محظور' : 'نشط'));
        $this->newLine();

        $rows = [];

        foreach (StaffPermissions::PERMISSIONS as $permission => $label) {
            $rows[] = [$label, $user->hasPermission($permission) ? 'نعم' : '—'];
        }

        $this->table(['الصلاحية', 'ممنوحة'], $rows);

        return self::SUCCESS;
    }
}
