<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * إنشاء منتج من اللوحة — الحالات التي أسقطت الحفظ فعلًا.
 *
 * ثلاثة أخطاء أبلغ عنها المالك، وكلها من النوع نفسه: **عمود في قاعدة البيانات
 * أشدّ صرامة من النموذج**. فيقول النموذج «اختياري» وتقول القاعدة «NOT NULL»،
 * فيسقط الحفظ بخطأ قاعدة بيانات لا برسالة مفهومة للمالك.
 */
class ProductCreationTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): Category
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Category::firstOrCreate(['slug' => 'ties'], ['name_ar' => 'كرافات', 'name_en' => 'Ties']);
    }

    private function baseForm(Category $category): array
    {
        return [
            'name_ar' => 'منتج جديد',
            'category_id' => $category->id,
            'price_usd' => 10,
            'product_type' => Product::TYPE_SINGLE,
            'variants' => [['quantity' => 2]],
        ];
    }

    // ═══════════════ الاسم الإنجليزي ═══════════════

    public function test_a_product_can_be_created_without_an_english_name(): void
    {
        $category = $this->boot();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->baseForm($category))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name_ar', 'منتج جديد')->first();

        $this->assertNotNull($product);
        $this->assertNull($product->name_en);
        // الاسم العربي يسدّ الفراغ في الروابط والعرض
        $this->assertNotEmpty($product->slug);
    }

    public function test_the_english_name_is_still_used_when_provided(): void
    {
        $category = $this->boot();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->baseForm($category) + ['name_en' => 'New Product'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('New Product', Product::where('name_ar', 'منتج جديد')->value('name_en'));
    }

    // ═══════════════ رفع الصورة ═══════════════

    public function test_a_product_can_be_created_with_an_uploaded_image(): void
    {
        $category = $this->boot();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->baseForm($category) + [
                'media' => [[
                    'file_path' => [UploadedFile::fake()->image('photo.jpg', 400, 400)],
                    'is_main' => true,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name_ar', 'منتج جديد')->first();

        $this->assertNotNull($product);
        $this->assertSame(1, $product->media()->count());
        $this->assertSame('image', $product->media()->first()->type);
    }

    /**
     * أسماء الملفات القادمة من تيليجرام تحوي `=` و`-` — وقد رفع المالك ملفًا
     * بهذا الشكل. الاختبار يضمن ألا يكون الاسم سببًا في السقوط.
     */
    public function test_a_filename_with_equals_and_dashes_is_accepted(): void
    {
        $category = $this->boot();
        $name = 'metacGhvdG9fMjAyNi0wOS0xMF8wNC01Mi0zNC5qcGc=-.jpg';

        Livewire::test(CreateProduct::class)
            ->fillForm($this->baseForm($category) + [
                'media' => [['file_path' => [UploadedFile::fake()->image($name, 300, 300)], 'is_main' => true]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Product::where('name_ar', 'منتج جديد')->first()->media()->count());
    }

    public function test_the_media_sort_order_never_reaches_the_database_as_null(): void
    {
        // حقل «الترتيب» يُترك فارغًا في النموذج ⇒ كان يُسند null صراحةً
        // فيسقط الحفظ بـ NOT NULL constraint failed: product_media.sort_order
        $category = $this->boot();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->baseForm($category) + [
                'media' => [['file_path' => [UploadedFile::fake()->image('x.jpg')], 'sort_order' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, (int) Product::where('name_ar', 'منتج جديد')->first()->media()->first()->sort_order);
    }

    // ═══════════════ حقل اللون للمنتج الفردي ═══════════════

    public function test_a_single_product_still_has_colour_and_size_fields(): void
    {
        $this->boot();

        $html = Livewire::test(CreateProduct::class)
            ->assertFormSet(['product_type' => Product::TYPE_SINGLE])
            ->html();

        // المنتج الفردي له لون ومقاس يُسجَّلهما المالك
        $this->assertStringContainsString('اسم اللون', $html);
        $this->assertStringContainsString('درجة اللون', $html);
        $this->assertStringContainsString('المقاس', $html);
        $this->assertGreaterThan(0, substr_count($html, 'fi-fo-color-picker'), 'منتقي اللون يجب أن يظهر');
    }

    public function test_a_single_product_saves_its_colour(): void
    {
        $category = $this->boot();

        Livewire::test(CreateProduct::class)
            ->fillForm(array_merge($this->baseForm($category), [
                'variants' => [['color' => 'كحلي', 'color_hex' => '#1B2A4A', 'quantity' => 3]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $variant = Product::where('name_ar', 'منتج جديد')->first()->variants()->first();

        $this->assertSame('كحلي', $variant->color);
        $this->assertSame('#1B2A4A', $variant->color_hex);
    }

    public function test_a_single_product_offers_no_add_or_remove_buttons(): void
    {
        $this->boot();

        $single = Livewire::test(CreateProduct::class)
            ->assertFormSet(['product_type' => Product::TYPE_SINGLE])
            ->html();

        // «قطعة واحدة» = صف واحد لا يُضاف ولا يُحذف — والقيد في الواجهة
        $this->assertStringNotContainsString('أضف لونًا', $single, 'لا زر إضافة في المنتج الفردي');

        $variant = Livewire::test(CreateProduct::class)
            ->fillForm(['product_type' => Product::TYPE_VARIANT])
            ->html();

        $this->assertStringContainsString('أضف لونًا', $variant, 'المنتج المكرر يملك زر إضافة');
    }
}
