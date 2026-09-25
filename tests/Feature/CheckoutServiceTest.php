<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * اختبارات إنشاء الطلب: الخصم من المخزون، تسجيل الحالة، الكوبون،
 * ورفض الطلب عند نفاد المخزون دون ترك أي أثر.
 */
class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithStock(int $quantity, float $price = 10.0): ProductVariant
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

        return ProductVariant::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'low_stock_threshold' => 3,
        ]);
    }

    private function putCart(ProductVariant $variant, int $qty): void
    {
        Session::put(CartService::SESSION_KEY, [
            'v'.$variant->id => ['key' => 'v'.$variant->id, 'qty' => $qty],
        ]);
    }

    public function test_placing_order_deducts_stock_and_creates_history(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithStock(10);

        $this->putCart($variant, 3);

        $order = CheckoutService::place($user, ['shipping_method' => 'pickup']);

        // المخزون
        $this->assertSame(7, (int) $variant->fresh()->quantity);
        $movement = StockMovement::latest('id')->first();
        $this->assertSame('sale', $movement->type);
        $this->assertSame(-3, (int) $movement->quantity);
        $this->assertSame($order->id, (int) $movement->order_id);

        // الطلب
        $this->assertSame(1, Order::count());
        $this->assertSame('pending', $order->status);
        $this->assertMatchesRegularExpression('/^NDF-[A-Z0-9]{6}$/', $order->order_code);
        $this->assertEquals(30.0, (float) $order->total_usd);
        $this->assertEquals(0.0, (float) $order->shipping_usd, 'الاستلام من المحل مجاني');

        // العناصر
        $this->assertSame(1, $order->items()->count());
        $item = $order->items()->first();
        $this->assertSame(3, (int) $item->quantity);
        $this->assertEquals(10.0, (float) $item->unit_price_usd);
        $this->assertEquals(6.0, (float) $item->unit_cost_usd, 'تُحفظ تكلفة الشراء لحساب الأرباح');

        // سجل الحالة
        $this->assertSame(1, OrderStatusHistory::count());
        $this->assertSame('pending', OrderStatusHistory::first()->to_status);

        // السلة تُفرَّغ
        $this->assertSame(0, CartService::count());
    }

    public function test_order_is_rejected_when_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithStock(2);

        $this->putCart($variant, 5);

        try {
            CheckoutService::place($user, ['shipping_method' => 'pickup']);
            $this->fail('كان يجب أن يُرمى ValidationException لنقص المخزون');
        } catch (ValidationException) {
            // متوقع
        }

        $this->assertSame(0, Order::count(), 'لم يُنشأ أي طلب');
        $this->assertSame(2, (int) $variant->fresh()->quantity, 'المخزون لم يُلمس');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_empty_cart_is_rejected(): void
    {
        $user = User::factory()->create();

        Session::forget(CartService::SESSION_KEY);

        $this->expectException(ValidationException::class);

        CheckoutService::place($user, ['shipping_method' => 'pickup']);
    }

    public function test_coupon_is_applied_and_counted(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithStock(10);

        $coupon = Coupon::create([
            'code' => 'TEST10',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->putCart($variant, 3); // المجموع 30$

        $order = CheckoutService::place($user, [
            'shipping_method' => 'pickup',
            'coupon_code' => 'TEST10',
        ]);

        $this->assertEquals(5.0, (float) $order->discount_usd);
        $this->assertEquals(25.0, (float) $order->total_usd);
        $this->assertSame($coupon->id, (int) $order->coupon_id);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $user = User::factory()->create();
        $variant = $this->variantWithStock(10);

        Coupon::create([
            'code' => 'EXPIRED',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'expiry_date' => now()->subDay(),
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->putCart($variant, 1);

        $this->expectException(ValidationException::class);

        CheckoutService::place($user, [
            'shipping_method' => 'pickup',
            'coupon_code' => 'EXPIRED',
        ]);
    }
}
