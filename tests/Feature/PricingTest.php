<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * اختبارات التسعير: الجملة، سعر الصرف، الشحن، الكوبون.
 *
 * هذه أكثر بقعة حساسة ماليًا في المشروع — خطأ هنا يعني فاتورة خاطئة
 * للعميل أو هامش ربح سالب، ويُكتشف بعد البيع لا قبله.
 */
class PricingTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = [], int $stock = 100): Product
    {
        $category = Category::create([
            'name_ar' => 'كرفات',
            'name_en' => 'Ties',
            'slug' => 'ties-'.uniqid(),
        ]);

        $product = Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'كرافتة',
            'name_en' => 'Tie',
            'slug' => 'tie-'.uniqid(),
            'price_usd' => 10,
            'cost_usd' => 6,
        ], $attributes));

        ProductVariant::create([
            'product_id' => $product->id,
            'quantity' => $stock,
            'low_stock_threshold' => 3,
        ]);

        return $product->refresh();
    }

    private function putInCart(Product $product, int $qty): void
    {
        $variant = $product->variants()->first();

        Session::put(CartService::SESSION_KEY, [
            'v'.$variant->id => ['key' => 'v'.$variant->id, 'qty' => $qty],
        ]);
    }

    // ---------- سعر الوحدة ----------

    public function test_wholesale_price_applies_only_when_requested(): void
    {
        $product = $this->product(['wholesale_price_usd' => 7.5]);

        $this->assertSame(10.0, $product->unitPriceUsd(false));
        $this->assertSame(7.5, $product->unitPriceUsd(true));
    }

    public function test_missing_wholesale_price_falls_back_to_retail(): void
    {
        $product = $this->product(['wholesale_price_usd' => null]);

        $this->assertSame(10.0, $product->unitPriceUsd(true));
    }

    // ---------- سعر الليرة ----------

    public function test_syp_price_is_computed_from_exchange_rate_rounded_to_hundred(): void
    {
        Setting::set('exchange_rate', '15000');
        $product = $this->product(['price_usd' => 12.34]);

        // 12.34 × 15000 = 185,100 → مقرّب لأقرب 100 = 185,100
        $this->assertSame(185100.0, $product->priceSyp(false));

        Setting::set('exchange_rate', '12345');
        $product->refresh();

        // 12.34 × 12345 = 152,337.3 → 152,300
        $this->assertSame(152300.0, $product->priceSyp(false));
    }

    public function test_manual_syp_price_overrides_computation_for_retail_only(): void
    {
        Setting::set('exchange_rate', '15000');
        $product = $this->product([
            'price_usd' => 10,
            'wholesale_price_usd' => 7,
            'manual_price_syp' => 99999,
        ]);

        $this->assertSame(99999.0, $product->priceSyp(false), 'التجاوز اليدوي للمفرق');
        $this->assertSame(105000.0, $product->priceSyp(true), 'الجملة تُحسب من سعر الصرف دائمًا');
    }

    // ---------- متى تُفعَّل الجملة ----------

    public function test_wholesale_triggers_at_minimum_quantity(): void
    {
        Setting::set('wholesale_min_quantity', '10');
        Setting::set('wholesale_min_amount_usd', '200');

        $product = $this->product(['wholesale_price_usd' => 7]);

        $this->putInCart($product, 9);
        $this->assertFalse(CartService::detailed()->first()->is_wholesale);

        $this->putInCart($product, 10);
        $this->assertTrue(CartService::detailed()->first()->is_wholesale);
    }

    public function test_wholesale_triggers_at_minimum_amount(): void
    {
        Setting::set('wholesale_min_quantity', '50');
        Setting::set('wholesale_min_amount_usd', '200');

        // 25 × 10$ = 250$ ≥ 200$ مع أن الكمية أقل من 50
        $product = $this->product(['wholesale_price_usd' => 7]);

        $this->putInCart($product, 25);

        $item = CartService::detailed()->first();
        $this->assertTrue($item->is_wholesale);
        $this->assertSame(7.0, $item->unit_usd);
    }

    public function test_hidden_wholesale_on_product_blocks_it(): void
    {
        Setting::set('wholesale_min_quantity', '1');

        $product = $this->product(['wholesale_price_usd' => 7, 'hide_wholesale' => true]);

        $this->putInCart($product, 10);

        $item = CartService::detailed()->first();
        $this->assertFalse($item->is_wholesale);
        $this->assertSame(10.0, $item->unit_usd);
    }

    public function test_global_hide_wholesale_setting_blocks_it(): void
    {
        Setting::set('wholesale_min_quantity', '1');
        Setting::set('hide_wholesale_button', '1');

        $product = $this->product(['wholesale_price_usd' => 7]);

        $this->putInCart($product, 10);

        $this->assertFalse(CartService::detailed()->first()->is_wholesale);
    }

    // ---------- الشحن والإجماليات ----------

    public function test_shipping_fee_applies_to_local_delivery_only(): void
    {
        Setting::set('shipping_enabled', '1');
        Setting::set('shipping_fee_usd', '2');

        $product = $this->product();
        $this->putInCart($product, 2);   // 20$

        $pickup = CartService::totals('pickup');
        $this->assertSame(0.0, $pickup['shipping_usd']);
        $this->assertSame(20.0, $pickup['total_usd']);

        $local = CartService::totals('local');
        $this->assertSame(2.0, $local['shipping_usd']);
        $this->assertSame(22.0, $local['total_usd']);
    }

    public function test_shipping_is_free_when_disabled(): void
    {
        Setting::set('shipping_enabled', '0');
        Setting::set('shipping_fee_usd', '5');

        $product = $this->product();
        $this->putInCart($product, 1);

        $this->assertSame(0.0, CartService::totals('local')['shipping_usd']);
    }

    public function test_totals_freeze_the_current_exchange_rate(): void
    {
        Setting::set('exchange_rate', '15000');

        $product = $this->product(['price_usd' => 10]);
        $this->putInCart($product, 2);

        $totals = CartService::totals('pickup');

        $this->assertSame(15000.0, $totals['exchange_rate']);
        $this->assertSame(300000.0, $totals['total_syp']);
    }

    // ---------- الكوبون ----------

    public function test_percentage_coupon_is_applied_on_subtotal(): void
    {
        $product = $this->product();
        $this->putInCart($product, 10);   // 100$

        $coupon = Coupon::create([
            'code' => 'P10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $totals = CartService::totals('pickup', $coupon);

        $this->assertSame(100.0, $totals['subtotal_usd']);
        $this->assertSame(10.0, $totals['discount_usd']);
        $this->assertSame(90.0, $totals['total_usd']);
    }

    public function test_fixed_coupon_never_exceeds_the_subtotal(): void
    {
        $product = $this->product();
        $this->putInCart($product, 1);   // 10$

        $coupon = Coupon::create([
            'code' => 'BIG',
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $totals = CartService::totals('pickup', $coupon);

        $this->assertSame(10.0, $totals['discount_usd'], 'الخصم لا يتجاوز المجموع الفرعي');
        $this->assertSame(0.0, $totals['total_usd'], 'ولا يصبح الإجمالي سالبًا');
    }

    public function test_coupon_below_minimum_order_is_not_applied(): void
    {
        $product = $this->product();
        $this->putInCart($product, 1);   // 10$

        $coupon = Coupon::create([
            'code' => 'MIN50',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'min_order_usd' => 50,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->assertFalse($coupon->isValid(10.0));
        $this->assertSame(0.0, CartService::totals('pickup', $coupon)['discount_usd']);
    }

    public function test_expired_coupon_is_invalid(): void
    {
        $coupon = Coupon::create([
            'code' => 'OLD',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'expiry_date' => now()->subDay(),
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->assertFalse($coupon->isValid(100.0));
    }

    public function test_coupon_is_invalid_after_reaching_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'ONCE',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'usage_limit' => 3,
            'used_count' => 3,
            'is_active' => true,
        ]);

        $this->assertFalse($coupon->isValid(100.0));
    }

    public function test_inactive_coupon_is_invalid(): void
    {
        $coupon = Coupon::create([
            'code' => 'OFF',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'used_count' => 0,
            'is_active' => false,
        ]);

        $this->assertFalse($coupon->isValid(100.0));
    }

    protected function tearDown(): void
    {
        Session::flush();

        parent::tearDown();
    }
}
