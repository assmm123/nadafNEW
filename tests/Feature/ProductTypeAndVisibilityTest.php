<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * نوع المنتج (فردي/مكرر) وأعلام الظهور للعميل.
 *
 * سبب وجود هذه الاختبارات: الفرق بين الفردي والمكرر كان **غير ظاهر** في
 * النموذج (ستة أعمدة متغيرات لمن يبيع قطعة واحدة)، وكان المنتج بلا متغيرات
 * يظهر «نفدت» في اللوحة و«متوفر» في المتجر. كلاهما مُغطّى هنا.
 */
class ProductTypeAndVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function category(): Category
    {
        // firstOrCreate: القسم واحد لكل الاختبارات — الـslug عمود فريد
        return Category::firstOrCreate(
            ['slug' => 'ties'],
            ['name_ar' => 'كرافات', 'name_en' => 'Ties'],
        );
    }

    private function product(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category()->id,
            'name_ar' => 'منتج',
            'name_en' => 'Product',
            'slug' => 'p-'.uniqid(),
            'price_usd' => 10,
        ], $attrs));
    }

    // ═══════════════ نوع المنتج ═══════════════

    public function test_a_new_product_defaults_to_single(): void
    {
        $this->assertTrue($this->product()->isSingle());
        $this->assertFalse($this->product()->isVariant());
    }

    public function test_the_type_is_stored_not_inferred_from_variant_count(): void
    {
        // منتج «مكرر» بلا متغيرات يبقى مكررًا — العدد لا يُستنتج منه النوع
        $variant = $this->product(['product_type' => Product::TYPE_VARIANT]);
        $this->assertSame(0, $variant->variants()->count());
        $this->assertTrue($variant->isVariant());
    }

    public function test_a_single_product_shows_colour_but_no_add_button(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Livewire::test(CreateProduct::class)
            ->assertFormSet(['product_type' => Product::TYPE_SINGLE])
            ->html();

        // المنتج الفردي له لون ومقاس يُسجَّلهما المالك…
        $this->assertGreaterThan(0, substr_count($html, 'fi-fo-color-picker'), 'المنتج الفردي يعرض منتقي اللون');
        $this->assertStringContainsString('اسم اللون', $html);
        // …لكن صفّه واحد لا يُضاف إليه ولا يُحذف منه
        $this->assertStringNotContainsString('أضف لونًا', $html, 'المنتج الفردي لا يملك زر إضافة متغير');
    }

    public function test_the_form_shows_colour_fields_for_a_variant_product(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Livewire::test(CreateProduct::class)
            ->fillForm(['product_type' => Product::TYPE_VARIANT])
            ->html();

        $this->assertGreaterThan(0, substr_count($html, 'fi-fo-color-picker'));
        $this->assertStringContainsString('أضف لونًا', $html);
    }

    // ═══════════════ تنبيه الاسم المتكرر ═══════════════

    public function test_a_duplicate_name_raises_a_warning(): void
    {
        $this->product(['name_ar' => 'اسم مكرر', 'name_en' => 'Dup', 'slug' => 'dup-1']);

        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Livewire::test(CreateProduct::class)
            ->fillForm(['name_ar' => 'اسم مكرر'])
            ->html();

        $this->assertStringContainsString('يوجد منتج آخر بالاسم نفسه', $html);
    }

    public function test_the_warning_does_not_block_saving(): void
    {
        $this->product(['name_ar' => 'اسم مكرر', 'name_en' => 'Dup', 'slug' => 'dup-1']);

        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // التنبيه إرشادي: التكرار قد يكون مقصودًا (خامة أو سعر مختلف)
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name_ar' => 'اسم مكرر',
                'name_en' => 'Dup Two',
                'category_id' => $this->category()->id,
                'price_usd' => 12,
                'product_type' => Product::TYPE_SINGLE,
                'variants' => [['quantity' => 3]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Product::where('name_ar', 'اسم مكرر')->count());
    }

    public function test_a_unique_name_raises_no_warning(): void
    {
        $this->product(['name_ar' => 'اسم أول', 'name_en' => 'One', 'slug' => 'one']);

        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Livewire::test(CreateProduct::class)
            ->fillForm(['name_ar' => 'اسم ثانٍ مختلف تمامًا'])
            ->html();

        $this->assertStringNotContainsString('يوجد منتج آخر بالاسم نفسه', $html);
    }

    // ═══════════════ تناقض المخزون ═══════════════

    public function test_a_product_without_variants_is_consistent_between_panel_and_store(): void
    {
        $product = $this->product();

        $this->assertTrue($product->inStock(), 'المتجر يعتبره متوفرًا');
        $this->assertSame('unlimited', $product->stockInfo()['kind'], 'واللوحة يجب أن تقول الشيء نفسه');
        $this->assertSame('غير محدود', $product->stockInfo()['label']);
    }

    public function test_stock_info_matches_in_stock_for_every_case(): void
    {
        $out = $this->product(['name_ar' => 'نافد', 'name_en' => 'Out', 'slug' => 'out']);
        ProductVariant::create(['product_id' => $out->id, 'quantity' => 0, 'low_stock_threshold' => 3]);

        $low = $this->product(['name_ar' => 'قليل', 'name_en' => 'Low', 'slug' => 'low']);
        ProductVariant::create(['product_id' => $low->id, 'quantity' => 2, 'low_stock_threshold' => 3]);

        $ok = $this->product(['name_ar' => 'متوفر', 'name_en' => 'Ok', 'slug' => 'ok']);
        ProductVariant::create(['product_id' => $ok->id, 'quantity' => 20, 'low_stock_threshold' => 3]);

        $this->assertSame('out', $out->fresh()->stockInfo()['kind']);
        $this->assertSame('low', $low->fresh()->stockInfo()['kind']);
        $this->assertSame('ok', $ok->fresh()->stockInfo()['kind']);

        // التناقض الذي كان: اللوحة تقول نفدت والمتجر يقول متوفر
        $this->assertFalse($out->fresh()->inStock());
        $this->assertTrue($low->fresh()->inStock());
        $this->assertTrue($ok->fresh()->inStock());
    }

    // ═══════════════ أعلام الظهور ═══════════════

    public function test_a_product_can_hide_its_price(): void
    {
        $product = $this->product(['hide_price' => true]);

        $this->assertTrue($product->hidesPrice());
        $this->assertTrue(product_price_hidden($product));
        $this->assertFalse(product_shows_unit_price($product));
    }

    public function test_hiding_the_unit_price_keeps_the_wholesale_visible(): void
    {
        $product = $this->product([
            'hide_unit_price' => true,
            'wholesale_price_usd' => 7,
        ]);

        $this->assertFalse(product_shows_unit_price($product));
        $this->assertTrue(product_shows_wholesale($product));
        // السعر ككل ليس مخفيًا — أُخفي جزء منه فقط
        $this->assertFalse(product_price_hidden($product));
    }

    public function test_the_global_setting_overrides_the_product_flag(): void
    {
        // المتجر كله في وضع «إخفاء كل الأسعار» ⇒ لا منتج يُظهر سعرًا
        Setting::set('price_display_mode', 'none');

        $product = $this->product(['wholesale_price_usd' => 5]);

        $this->assertTrue(product_price_hidden($product));
        $this->assertFalse(product_shows_unit_price($product));
        $this->assertFalse(product_shows_wholesale($product));
    }

    public function test_a_product_can_hide_its_colours(): void
    {
        $product = $this->product(['hide_colors' => true, 'product_type' => Product::TYPE_VARIANT]);
        ProductVariant::create(['product_id' => $product->id, 'color' => 'كحلي', 'quantity' => 3]);

        $this->assertTrue($product->hidesColors());
        $this->assertFalse(product_shows_colors($product));
        $this->assertCount(0, $product->fresh()->visibleVariants());
    }

    public function test_the_flags_default_to_visible(): void
    {
        $product = $this->product(['wholesale_price_usd' => 5]);

        $this->assertFalse($product->hidesPrice());
        $this->assertFalse($product->hidesUnitPrice());
        $this->assertFalse($product->hidesWholesale());
        $this->assertFalse($product->hidesColors());
        $this->assertTrue(product_shows_unit_price($product));
        $this->assertTrue(product_shows_colors($product));
    }
}
