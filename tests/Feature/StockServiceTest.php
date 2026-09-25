<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات سجل حركات المخزون — أهم منطق مالي في المشروع.
 * الهدف: ضمان أن الرصيد والحركة يتغيّران معًا، وأن المخزون لا يقبل السالب أبدًا.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    /** منتج + متغير برصيد محدد */
    private function variantWithStock(int $quantity, int $threshold = 3): ProductVariant
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
            'price_usd' => 10,
            'cost_usd' => 6,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'low_stock_threshold' => $threshold,
        ]);
    }

    public function test_sale_decrements_stock_and_logs_movement(): void
    {
        $variant = $this->variantWithStock(10);

        StockService::record($variant->id, 'sale', -4, null, null, 'بيع تجريبي');

        $this->assertSame(6, (int) $variant->fresh()->quantity);

        $movement = StockMovement::latest('id')->first();

        $this->assertNotNull($movement);
        $this->assertSame('sale', $movement->type);
        $this->assertSame(-4, (int) $movement->quantity);
        $this->assertSame(10, (int) $movement->balance_before);
        $this->assertSame(6, (int) $movement->balance_after);
        $this->assertSame('بيع تجريبي', $movement->note);
    }

    public function test_negative_balance_is_rejected_and_nothing_is_persisted(): void
    {
        $variant = $this->variantWithStock(2);

        try {
            StockService::record($variant->id, 'sale', -5);
            $this->fail('كان يجب أن يُرمى InsufficientStockException');
        } catch (InsufficientStockException) {
            // متوقع — الحارس يعمل
        }

        $this->assertSame(2, (int) $variant->fresh()->quantity, 'الرصيد لم يتغيّر');
        $this->assertSame(0, StockMovement::count(), 'لم تُسجَّل أي حركة');
    }

    public function test_return_restores_quantity_after_sale(): void
    {
        $variant = $this->variantWithStock(5);

        StockService::record($variant->id, 'sale', -5);
        $this->assertSame(0, (int) $variant->fresh()->quantity);

        StockService::record($variant->id, 'return', 5);
        $this->assertSame(5, (int) $variant->fresh()->quantity);

        $this->assertSame(2, StockMovement::count());
    }

    public function test_adjust_applies_the_difference(): void
    {
        $variant = $this->variantWithStock(4);

        // تسوية إلى 9 = حركة +5
        StockService::record($variant->id, 'adjust', 9 - 4);

        $this->assertSame(9, (int) $variant->fresh()->quantity);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame('adjust', $movement->type);
        $this->assertSame(5, (int) $movement->quantity);
    }

    public function test_can_withdraw_matches_available_quantity(): void
    {
        $variant = $this->variantWithStock(3);

        $this->assertTrue(StockService::canWithdraw($variant->id, 3));
        $this->assertFalse(StockService::canWithdraw($variant->id, 4));
    }
}
