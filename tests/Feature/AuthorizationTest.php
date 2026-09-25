<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Support\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * اختبارات الصلاحيات — كل خلية في المصفوفة يجب أن تكون مضمونة.
 *
 * صلاحية مكسورة أخطر من ميزة ناقصة: «مدير متجر» يستطيع تعديل حساب المالك
 * يعني قفل المتجر على صاحبه. لذلك هذه أهم اختبارات المشروع بعد المخزون.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->make(['role' => $role]);
    }

    // ---------- المصفوفة نفسها ----------

    public function test_owner_has_every_permission(): void
    {
        $owner = $this->staff(StaffPermissions::ROLE_OWNER);

        foreach (['orders.view', 'users.delete', 'settings.manage', 'reports.view', 'stock.adjust'] as $permission) {
            $this->assertTrue($owner->hasPermission($permission), "المالك يجب أن يملك {$permission}");
        }
    }

    public function test_legacy_admin_role_is_treated_as_owner(): void
    {
        $legacy = $this->staff(StaffPermissions::LEGACY_OWNER);

        $this->assertTrue($legacy->isOwner());
        $this->assertTrue($legacy->isAdmin());
        $this->assertTrue($legacy->hasPermission('users.delete'));
        $this->assertTrue($legacy->hasPermission('settings.manage'));
    }

    public function test_manager_manages_the_shop_but_not_users_or_settings(): void
    {
        $manager = $this->staff(StaffPermissions::ROLE_MANAGER);

        $this->assertTrue($manager->hasPermission('orders.update'));
        $this->assertTrue($manager->hasPermission('orders.delete'));
        $this->assertTrue($manager->hasPermission('products.delete'));
        $this->assertTrue($manager->hasPermission('reports.view'));

        $this->assertFalse($manager->hasPermission('users.view'));
        $this->assertFalse($manager->hasPermission('users.update'));
        $this->assertFalse($manager->hasPermission('settings.manage'));
    }

    public function test_warehouse_sees_stock_only(): void
    {
        $warehouse = $this->staff(StaffPermissions::ROLE_WAREHOUSE);

        $this->assertTrue($warehouse->hasPermission('products.view'));
        $this->assertTrue($warehouse->hasPermission('stock.adjust'));
        $this->assertTrue($warehouse->hasPermission('purchasing.update'));

        $this->assertFalse($warehouse->hasPermission('orders.view'), 'لا يرى الطلبات');
        $this->assertFalse($warehouse->hasPermission('products.update'), 'لا يعدّل المنتجات والأسعار');
        $this->assertFalse($warehouse->hasPermission('reports.view'), 'لا يرى الأرباح');
        $this->assertFalse($warehouse->hasPermission('users.view'));
    }

    public function test_support_reads_orders_without_deleting(): void
    {
        $support = $this->staff(StaffPermissions::ROLE_SUPPORT);

        $this->assertTrue($support->hasPermission('orders.view'));
        $this->assertTrue($support->hasPermission('orders.update'));

        $this->assertFalse($support->hasPermission('orders.delete'), 'لا يلغي الطلبات');
        $this->assertFalse($support->hasPermission('products.view'));
        $this->assertFalse($support->hasPermission('reports.view'));
    }

    public function test_customer_has_no_staff_permission(): void
    {
        $customer = $this->staff(StaffPermissions::ROLE_CUSTOMER);

        $this->assertFalse($customer->isAdmin());
        $this->assertFalse($customer->isOwner());
        $this->assertFalse($customer->hasPermission('orders.view'));
        $this->assertFalse($customer->hasPermission('products.view'));
    }

    // ---------- الربط الفعلي بالسياسات ----------

    public function test_policies_are_discovered_for_models(): void
    {
        $this->assertInstanceOf(OrderPolicy::class, Gate::getPolicyFor(Order::class));
        $this->assertNotNull(Gate::getPolicyFor(Product::class));
        $this->assertNotNull(Gate::getPolicyFor(PurchaseInvoice::class));
        $this->assertNotNull(Gate::getPolicyFor(User::class));
    }

    public function test_gate_denies_and_allows_by_role(): void
    {
        $owner = $this->staff(StaffPermissions::ROLE_OWNER);
        $manager = $this->staff(StaffPermissions::ROLE_MANAGER);
        $warehouse = $this->staff(StaffPermissions::ROLE_WAREHOUSE);
        $support = $this->staff(StaffPermissions::ROLE_SUPPORT);

        // الطلبات
        $this->assertTrue(Gate::forUser($support)->allows('viewAny', Order::class));
        $this->assertFalse(Gate::forUser($warehouse)->allows('viewAny', Order::class));
        $this->assertTrue(Gate::forUser($manager)->allows('viewAny', Order::class));

        // المنتجات: مسؤول المخزون يعرض ولا يعدّل
        $this->assertTrue(Gate::forUser($warehouse)->allows('viewAny', Product::class));
        $this->assertFalse(Gate::forUser($warehouse)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', Product::class));

        // المستخدمون: المالك وحده
        $this->assertFalse(Gate::forUser($manager)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', User::class));
    }

    // ---------- حماية حساب المالك ----------

    public function test_nobody_but_owner_can_update_the_owner_account(): void
    {
        $owner = User::factory()->create(['role' => StaffPermissions::ROLE_OWNER]);
        $manager = $this->staff(StaffPermissions::ROLE_MANAGER);

        $this->assertFalse(
            Gate::forUser($manager)->allows('update', $owner),
            'مدير المتجر لا يعدّل حساب المالك — وإلا يغيّر كلمة مروره أو يحظره'
        );
    }

    public function test_even_a_granted_user_admin_cannot_touch_the_owner(): void
    {
        // لو مُنح دورٌ ما صلاحية إدارة المستخدمين مستقبلًا، تبقى حماية المالك سارية
        $owner = User::factory()->create(['role' => StaffPermissions::ROLE_OWNER]);
        $other = $this->staff(StaffPermissions::ROLE_MANAGER);

        $this->assertFalse(Gate::forUser($other)->allows('delete', $owner));
    }

    public function test_owner_can_update_own_account_but_not_delete_it(): void
    {
        $owner = User::factory()->create(['role' => StaffPermissions::ROLE_OWNER]);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $owner));
        $this->assertFalse(
            Gate::forUser($owner)->allows('delete', $owner),
            'لا يحذف المالك حسابه — يمنع قفل المتجر بلا مدير'
        );
    }

    public function test_owner_cannot_be_deleted_by_another_owner(): void
    {
        $owner = User::factory()->create(['role' => StaffPermissions::ROLE_OWNER]);
        $secondOwner = $this->staff(StaffPermissions::ROLE_OWNER);

        $this->assertFalse(Gate::forUser($secondOwner)->allows('delete', $owner));
    }

    // ---------- بوابة دخول اللوحة ----------

    public function test_customer_cannot_reach_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => StaffPermissions::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_blocked_staff_cannot_reach_the_admin_panel(): void
    {
        $blocked = User::factory()->create([
            'role' => StaffPermissions::ROLE_MANAGER,
            'status' => 'blocked',
        ]);

        $this->actingAs($blocked)->get('/admin')->assertForbidden();
    }

    // ---------- مسارات /admin خارج Filament ----------
    // هذه المسارات لا تمر عليها السياسات تلقائيًا (ليست موارد Filament)،
    // فكل واحد منها يحتاج فحصًا صريحًا. اختباراتها هنا تمنع نسيانها لاحقًا.

    private function createOrder(): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'order_code' => Order::generateCode(),
            'status' => 'pending',
            'subtotal_usd' => 10,
            'total_usd' => 10,
            'exchange_rate' => 15000,
            'total_syp' => 150000,
        ]);

        $order->items()->create([
            'name_ar' => 'كرافتة',
            'name_en' => 'Tie',
            'quantity' => 1,
            'unit_price_usd' => 10,
            'total_price_usd' => 10,
        ]);

        return $order;
    }

    public function test_warehouse_cannot_export_profit_reports(): void
    {
        $warehouse = User::factory()->create(['role' => StaffPermissions::ROLE_WAREHOUSE]);

        $this->actingAs($warehouse)
            ->get(route('admin.reports.export'))
            ->assertForbidden();
    }

    public function test_manager_can_export_profit_reports(): void
    {
        $manager = User::factory()->create(['role' => StaffPermissions::ROLE_MANAGER]);

        $this->actingAs($manager)
            ->get(route('admin.reports.export'))
            ->assertOk();
    }

    public function test_support_can_view_an_order_invoice(): void
    {
        $support = User::factory()->create(['role' => StaffPermissions::ROLE_SUPPORT]);

        $this->actingAs($support)
            ->get(route('admin.invoice', $this->createOrder()))
            ->assertOk();
    }

    public function test_warehouse_cannot_view_an_order_invoice(): void
    {
        $warehouse = User::factory()->create(['role' => StaffPermissions::ROLE_WAREHOUSE]);

        $this->actingAs($warehouse)
            ->get(route('admin.invoice', $this->createOrder()))
            ->assertForbidden();
    }
}
