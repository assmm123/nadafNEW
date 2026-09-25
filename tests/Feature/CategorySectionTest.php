<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * قسم «الأقسام» — بعد حذف «أقسامي».
 *
 * كان مدخلين للمجال نفسه («الأقسام» جدول · «أقسامي» بوابة كروت)، فحُذف الثاني
 * وبقي هذا لأنه مكان إضافة الأقسام فعلًا. وهذه الاختبارات تحرس أمرين:
 *   • ألّا يعود مدخل ثانٍ للمجال
 *   • أن يعمل التنظيم الجديد (التبويبات · إعادة الترتيب · التنبيهات)
 */
class CategorySectionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function category(string $name, array $attrs = []): Category
    {
        return Category::create(array_merge([
            'name_ar' => $name,
            'name_en' => 'Cat '.uniqid(),
            'slug' => 'c-'.uniqid(),
        ], $attrs));
    }

    // ═══════════════ مدخل واحد لا مدخلين ═══════════════

    public function test_the_my_categories_page_is_gone(): void
    {
        $this->assertFileDoesNotExist(app_path('Filament/Pages/MyCategories.php'));
        $this->assertFileDoesNotExist(resource_path('views/filament/pages/my-categories.blade.php'));
    }

    public function test_the_navigation_has_one_categories_entry(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $found = [];

        $walk = function ($items) use (&$walk, &$found) {
            foreach ($items as $item) {
                if ($item instanceof \Filament\Navigation\NavigationGroup) {
                    $walk($item->getItems());
                } elseif ($item instanceof \Filament\Navigation\NavigationItem) {
                    if (str_contains($item->getLabel(), 'أقسام')) {
                        $found[] = $item->getLabel();
                    }
                }
            }
        };

        $walk(Filament::getPanel('admin')->getNavigation());

        $this->assertSame(['الأقسام'], $found, 'يجب أن يكون مدخل الأقسام واحدًا فقط');
    }

    // ═══════════════ الصفحة والتبويبات ═══════════════

    public function test_the_page_renders_with_its_tabs(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(ManageCategories::class)->assertOk();

        $this->assertSame(
            ['all', 'main', 'sub', 'with_products', 'empty', 'hidden'],
            array_keys($page->instance()->getTabs())
        );
    }

    public function test_the_empty_tab_shows_only_categories_without_products(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $empty = $this->category('قسم فارغ');
        $filled = $this->category('قسم فيه منتج');
        Product::create([
            'category_id' => $filled->id, 'name_ar' => 'منتج', 'name_en' => 'P',
            'slug' => 'p-'.uniqid(), 'price_usd' => 10,
        ]);

        $html = Livewire::test(ManageCategories::class)
            ->set('activeTab', 'empty')
            ->html();

        $this->assertStringContainsString('قسم فارغ', $html);
        $this->assertStringNotContainsString('قسم فيه منتج', $html);
    }

    public function test_the_hidden_tab_shows_only_inactive_categories(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->category('ظاهر', ['is_active' => true]);
        $this->category('مخفي', ['is_active' => false]);

        $html = Livewire::test(ManageCategories::class)
            ->set('activeTab', 'hidden')
            ->html();

        $this->assertStringContainsString('مخفي', $html);
        $this->assertStringNotContainsString('ظاهر', $html);
    }

    // ═══════════════ إعادة الترتيب ═══════════════

    public function test_the_owner_may_reorder_categories(): void
    {
        $this->actingAs($this->owner());

        $this->assertTrue(\App\Filament\Resources\CategoryResource::canReorder());
    }

    public function test_a_support_role_may_not_reorder_categories(): void
    {
        // إعادة الترتيب فعل تعديل — ودعم العملاء لا يملك تعديل الأقسام
        $this->actingAs(User::factory()->create(['role' => 'support']));

        $this->assertFalse(\App\Filament\Resources\CategoryResource::canReorder());
    }

    // ═══════════════ تنبيه الاسم المتكرر ═══════════════

    public function test_a_duplicate_category_name_raises_a_warning(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->category('كرافات');

        // setActionData لا fillForm: هذه الصفحة تملك نموذجًا داخليًا باسم 'form'
        // فيتعارض مع نموذج الأكشن في أداة الاختبار.
        $html = Livewire::test(ManageCategories::class)
            ->mountAction('create')
            ->setActionData(['name_ar' => 'كرافات'])
            ->html();

        $this->assertStringContainsString('يوجد قسم آخر بالاسم نفسه', $html);
    }

    // ═══════════════ الإنشاء ═══════════════

    public function test_a_category_can_be_created_without_an_english_name(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageCategories::class)
            ->callAction('create', data: [
                'name_ar' => 'قسم بلا اسم إنجليزي',
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertHasNoActionErrors();

        $category = Category::where('name_ar', 'قسم بلا اسم إنجليزي')->first();

        $this->assertNotNull($category);
        $this->assertNotEmpty($category->slug, 'الرابط يُشتقّ من الاسم العربي');
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $category = $this->category('قسم');

        $html = Livewire::test(ManageCategories::class)
            ->mountAction('edit', arguments: ['record' => $category->getKey()])
            ->html();

        // القسم نفسه لا يظهر في قائمة «القسم الأب»
        $this->assertStringNotContainsString('value="'.$category->getKey().'" selected', $html);
    }
}
