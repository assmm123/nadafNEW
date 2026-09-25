<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * اختبارات سقف كمية السلة.
 *
 * الحراسة المهمة: المسارَان add() و update() يجب أن يطبّقا نفس السقف —
 * كان update() يحدّ المنتجات ذات المتغيرات فقط، فمرّت أي كمية عبره بلا حد.
 */
class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(bool $withVariant, int $stock = 0, float $price = 10.0): array
    {
        $category = Category::create([
            'name_ar' => 'كرفات',
            'name_en' => 'Ties',
            'slug' => 'ties-'.uniqid(),
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة',
            'name_en' => 'Tie',
            'slug' => 'tie-'.uniqid(),
            'price_usd' => $price,
            'cost_usd' => 6,
        ]);

        if (! $withVariant) {
            return ['product' => $product, 'variant' => null, 'key' => 'p'.$product->id];
        }

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'quantity' => $stock,
            'low_stock_threshold' => 3,
        ]);

        return ['product' => $product, 'variant' => $variant, 'key' => 'v'.$variant->id];
    }

    private function qtyInCart(string $key): int
    {
        return (int) (CartService::raw()[$key]['qty'] ?? 0);
    }

    public function test_add_caps_variant_less_product_at_setting_default(): void
    {
        $p = $this->makeProduct(withVariant: false);

        CartService::add($p['key'], 500);

        $this->assertSame(99, $this->qtyInCart($p['key']), 'السقف الافتراضي 99');
    }

    public function test_update_caps_variant_less_product_too(): void
    {
        $p = $this->makeProduct(withVariant: false);

        CartService::add($p['key'], 1);
        $this->assertSame(1, $this->qtyInCart($p['key']));

        // هذا هو الخطأ الأصلي: كان update() يمرّر أي كمية بلا سقف
        CartService::update($p['key'], 500);

        $this->assertSame(99, $this->qtyInCart($p['key']));
    }

    public function test_custom_setting_changes_the_cap_on_both_paths(): void
    {
        Setting::set('max_qty_per_item', '5');

        $p = $this->makeProduct(withVariant: false);

        CartService::add($p['key'], 500);
        $this->assertSame(5, $this->qtyInCart($p['key']));

        CartService::update($p['key'], 500);
        $this->assertSame(5, $this->qtyInCart($p['key']));
    }

    public function test_variant_product_is_capped_by_real_stock_on_both_paths(): void
    {
        $p = $this->makeProduct(withVariant: true, stock: 7);

        CartService::add($p['key'], 50);
        $this->assertSame(7, $this->qtyInCart($p['key']));

        CartService::update($p['key'], 50);
        $this->assertSame(7, $this->qtyInCart($p['key']));
    }

    public function test_setting_never_overrides_stock_for_variant_products(): void
    {
        Setting::set('max_qty_per_item', '500');

        $p = $this->makeProduct(withVariant: true, stock: 3);

        CartService::add($p['key'], 500);

        $this->assertSame(3, $this->qtyInCart($p['key']), 'المخزون له الأولوية دائمًا');
    }

    public function test_update_to_zero_removes_the_item(): void
    {
        $p = $this->makeProduct(withVariant: false);

        CartService::add($p['key'], 2);
        CartService::update($p['key'], 0);

        $this->assertArrayNotHasKey($p['key'], CartService::raw());
        $this->assertSame(0, CartService::count());
    }

    public function test_max_qty_helpers_agree(): void
    {
        Setting::set('max_qty_per_item', '12');

        $withoutVariant = $this->makeProduct(withVariant: false);
        $withVariant = $this->makeProduct(withVariant: true, stock: 4);

        $this->assertSame(12, CartService::maxQtyPerItem());
        $this->assertSame(12, CartService::maxQtyFor(['variant' => null]));
        $this->assertSame(4, CartService::maxQtyFor(['variant' => $withVariant['variant']]));
    }

    public function test_add_is_additive_but_still_respects_the_cap(): void
    {
        $p = $this->makeProduct(withVariant: false);

        CartService::add($p['key'], 60);
        CartService::add($p['key'], 60);

        $this->assertSame(99, $this->qtyInCart($p['key']));
    }

    protected function tearDown(): void
    {
        Session::flush();

        parent::tearDown();
    }
}
