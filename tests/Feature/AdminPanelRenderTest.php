<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * اختبار «كل صفحة في لوحة الأدمن تُفتح».
 *
 * سبب وجوده: صفحة الطلبات كانت **تسقط بخطأ 500** بسبب اسم وسيط كلوزر
 * في `ListOrders::getTabs()` — Filament لم يجد الوسيط بالاسم فحلّه بالنوع
 * عبر الحاوية، فصار موديل الجدول null وسقط الاستعلام كله:
 *
 *     Cannot use "::class" on null   (HasRecords::getModel)
 *
 * ولم يكشفه أي اختبار قائم، لأن الاختبارات تفحص الخدمات لا الواجهة.
 * هذا الاختبار يشغّل الصفحات فعليًا، فأي سقوط مشابه يُكشف فورًا.
 */
class AdminPanelRenderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): User
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $owner;
    }

    /** طلب بسيط — للصفحات التي لا ترسم شيئًا بلا سجل */
    private function makeOrder(User $owner, string $code, string $status = 'pending'): \App\Models\Order
    {
        return \App\Models\Order::create([
            'user_id' => $owner->id,
            'order_code' => $code,
            'status' => $status,
            'subtotal_usd' => 10,
            'discount_usd' => 0,
            'shipping_usd' => 0,
            'total_usd' => 10,
            'exchange_rate' => 15000,
            'total_syp' => 150000,
            'shipping_method' => 'pickup',
        ]);
    }

    /**
     * كل صفحة في اللوحة — الصفحات المخصّصة وصفحات فهرس الموارد.
     *
     * التكرار داخل الاختبار لا في data provider: مزوّد البيانات يعمل **قبل**
     * تشغيل التطبيق، فلوحة Filament تكون فارغة حينها ولا تُسجَّل مواردها بعد.
     */
    public function test_every_admin_page_renders(): void
    {
        $this->actingAsOwner();

        $panel = Filament::getPanel('admin');

        $targets = [];

        foreach ($panel->getPages() as $page) {
            $targets['صفحة: '.$page::getNavigationLabel()] = $page;
        }

        foreach ($panel->getResources() as $resource) {
            foreach ($resource::getPages() as $name => $registration) {
                if ($name === 'index') {
                    $targets['مورد: '.$resource::getNavigationLabel()] = $registration->getPage();
                }
            }
        }

        $this->assertNotEmpty($targets, 'يجب أن تُكتشف صفحات اللوحة');

        $failures = [];

        foreach ($targets as $label => $page) {
            try {
                Livewire::test($page)->assertOk();
            } catch (\Throwable $e) {
                $failures[] = $label.' → '.$e->getMessage();
            }
        }

        $this->assertSame([], $failures, "صفحات تسقط:\n".implode("\n", $failures));
    }

    public function test_the_orders_list_renders_with_its_tabs(): void
    {
        $this->actingAsOwner();

        // هذا هو الاختبار الذي كان سيكشف السقوط: الصفحة تبني جدولها وتبويباتها
        // كاملة، وأي فقدان لموديل الجدول يُسقطها هنا.
        Livewire::test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
            ->assertOk()
            ->assertSee('الكل');
    }

    public function test_the_orders_board_page_is_gone(): void
    {
        // «لوحة الطلبات» كانت قسمًا ثانيًا للمجال نفسه — أُزيلت كليًا.
        $this->assertFileDoesNotExist(app_path('Filament/Pages/OrdersBoard.php'));
        $this->assertFileDoesNotExist(resource_path('views/filament/pages/orders-board.blade.php'));
    }

    public function test_there_is_exactly_one_orders_entry_in_the_navigation(): void
    {
        $this->actingAsOwner();

        $found = [];

        $walk = function ($items) use (&$walk, &$found) {
            foreach ($items as $item) {
                if ($item instanceof \Filament\Navigation\NavigationGroup) {
                    $walk($item->getItems());
                } elseif ($item instanceof \Filament\Navigation\NavigationItem) {
                    if (str_contains($item->getLabel(), 'طلب')) {
                        $found[] = $item->getLabel();
                    }
                }
            }
        };

        $walk(Filament::getPanel('admin')->getNavigation());

        $this->assertSame(['الطلبات'], $found, 'يجب أن يكون قسم الطلبات واحدًا فقط');
    }

    public function test_the_orders_entry_is_the_second_item_after_the_dashboard(): void
    {
        $this->actingAsOwner();

        $labels = [];

        $walk = function ($items) use (&$walk, &$labels) {
            foreach ($items as $item) {
                if ($item instanceof \Filament\Navigation\NavigationGroup) {
                    // العناصر غير المجمَّعة تُعرض قبل المجموعات — نكتفي بالأولى
                    if ($labels === []) {
                        $walk($item->getItems());
                    }
                } elseif ($item instanceof \Filament\Navigation\NavigationItem) {
                    $labels[] = $item->getLabel();
                }
            }
        };

        $walk(Filament::getPanel('admin')->getNavigation());

        $this->assertSame('لوحة التحكم', $labels[0]);
        $this->assertSame('الطلبات', $labels[1]);
    }

    public function test_the_manual_order_action_is_available_on_the_orders_list(): void
    {
        $this->actingAsOwner();

        Livewire::test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
            ->mountAction('manualOrder')
            ->assertOk();
    }

    /**
     * طلب HTTP حقيقي عبر المكدّس كاملًا — لا `Livewire::test`.
     *
     * السبب: `Livewire::test()->html()` يُرجع عرض المكوّن وحده، **بلا رأس الصفحة**
     * الذي تُرسم فيه أزرار `getHeaderActions()`. فالفحص بالمكوّن يعطي «لا شيء»
     * كاذبًا. الفحص الصحيح هو نداء المسار فعلًا.
     */
    public function test_the_orders_page_serves_the_prototype_toolbar(): void
    {
        $this->actingAsOwner();

        $response = $this->get('/admin/orders')->assertOk();

        // شريط الأدوات — كما في النموذج
        $response->assertSee('طلب يدوي', escape: false);
        $response->assertSee('تصدير Excel', escape: false);
        $response->assertSee('الأرشيف', escape: false);

        // شريط مرحلة قبض الدفع
        $response->assertSee('مرحلة قبض الدفع', escape: false);

        // التبويبات الستة
        foreach (['الكل', 'جديدة', 'قيد التحضير', 'مشحون', 'مسلَّم', 'ملغى'] as $tab) {
            $response->assertSee($tab, escape: false);
        }
    }

    public function test_the_orders_table_serves_all_prototype_columns(): void
    {
        $owner = $this->actingAsOwner();

        // بلا سجل تُعرض «لا توجد نتائج» بدل الجدول، فلا تُرسم رؤوس الأعمدة أصلًا.
        $this->makeOrder($owner, 'NDF-COLS01');

        $response = $this->get('/admin/orders')->assertOk();

        foreach (['الكود', 'العميل', 'القطع', 'الإجمالي', 'الحالة', 'الدفع', 'التوثيق', 'التاريخ'] as $column) {
            $response->assertSee($column, escape: false);
        }
    }

    public function test_the_order_page_serves_the_five_tabs_and_actions(): void
    {
        $owner = $this->actingAsOwner();
        $order = $this->makeOrder($owner, 'NDF-TEST01');

        $response = $this->get('/admin/orders/'.$order->getRouteKey())->assertOk();

        foreach (['الملخص', 'المنتجات', 'الدفع والإثبات', 'التوصيل', 'السجل'] as $tab) {
            $response->assertSee($tab, escape: false);
        }

        foreach (['الفاتورة', 'تغيير الحالة', 'تم قبض الدفع', 'إلغاء الطلب'] as $action) {
            $response->assertSee($action, escape: false);
        }
    }

    public function test_the_invoice_page_serves_the_dark_identity(): void
    {
        $owner = $this->actingAsOwner();
        $order = $this->makeOrder($owner, 'NDF-TEST02');

        $response = $this->get(route('admin.invoice', $order))->assertOk();

        // الهوية الداكنة + الختم الأخضر
        $response->assertSee('0F1319', escape: false);
        $response->assertSee('الختم الأخضر', escape: false);
    }

    public function test_every_tab_query_closure_takes_its_argument_by_the_name_query(): void
    {
        // حماية من تكرار الخطأ: Filament يمرّر الوسيط باسم 'query'، وأي اسم آخر
        // يُحلّ بالنوع من الحاوية فيصير كائنًا فارغًا بلا موديل.
        $page = new \App\Filament\Resources\OrderResource\Pages\ListOrders;

        foreach ($page->getTabs() as $key => $tab) {
            $ref = new ReflectionMethod($tab, 'modifyQuery');
            $this->assertTrue($ref->isPublic(), "التبويب {$key} يجب أن يملك modifyQuery");
        }

        // الفحص الحقيقي: الصفحة تُعرض، وهذا لا ينجح إن كان اسم الوسيط خاطئًا
        $this->actingAsOwner();
        Livewire::test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)->assertOk();
    }
}
