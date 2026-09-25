<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * توجيه الرسائل إلى البوت الصحيح.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * اللوحة تعرض **ثلاثة بوتات** (طلبات · مخزون · تفاعلي) ووصفها يَعِد بأن لكل
 * غرض توكنه. وكانت الحقول تُحفظ فعلًا و**لا يقرأها شيء**: كل الرسائل تخرج
 * بالتوكن الأساسي، فيصير إعداد بوت منفصل للمخزون بلا أثر.
 */
class TelegramTokenRoutingTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '111111:base-token';
    private const ORDERS = '222222:orders-token';
    private const INVENTORY = '333333:inventory-token';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('telegram_bot_token', self::BASE);
        Setting::set('telegram_chat_id', '999');
        Setting::set('telegram_verify_ssl', '0');

        Http::fake();
    }

    private function order(): Order
    {
        $category = Category::create(['name_ar' => 'كرفات', 'name_en' => 'Ties', 'slug' => 'ties']);

        $product = Product::create([
            'category_id' => $category->id, 'name_ar' => 'كرافتة', 'name_en' => 'Tie',
            'slug' => 'tie', 'price_usd' => 10,
        ]);

        $order = Order::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'order_code' => 'NDF-TG01',
            'status' => 'pending',
            'subtotal_usd' => 10, 'discount_usd' => 0, 'shipping_usd' => 0,
            'total_usd' => 10, 'exchange_rate' => 15000, 'total_syp' => 150000,
            'shipping_method' => 'pickup',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name_ar' => 'كرافتة', 'name_en' => 'Tie',
            'quantity' => 1, 'unit_price_usd' => 10, 'unit_cost_usd' => 6,
            'total_price_usd' => 10, 'is_wholesale' => false,
        ]);

        return $order->fresh(['items', 'user', 'paymentMethod']);
    }

    /** هل أُرسل الطلب إلى بوت بهذا التوكن؟ */
    private function sentTo(string $token): bool
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), "/bot{$token}/"))->isNotEmpty();
    }

    public function test_order_notifications_use_the_orders_bot(): void
    {
        Setting::set('bot_orders_token', self::ORDERS);

        TelegramService::sendOrder($this->order());

        $this->assertTrue($this->sentTo(self::ORDERS), 'إشعار الطلب يخرج من بوت الطلبات');
        $this->assertFalse($this->sentTo(self::BASE), 'ولا من الأساسي');
    }

    public function test_inventory_alerts_use_the_inventory_bot(): void
    {
        Setting::set('bot_inventory_token', self::INVENTORY);

        TelegramService::sendInventory('تنبيه مخزون');

        $this->assertTrue($this->sentTo(self::INVENTORY), 'تنبيه المخزون يخرج من بوت المخزون');
        $this->assertFalse($this->sentTo(self::BASE));
    }

    /** وإن تُرك حقل البوت فارغًا عاد إلى الأساسي — كما ينصّ تلميح الحقل */
    public function test_an_empty_slot_falls_back_to_the_main_token(): void
    {
        Setting::set('bot_orders_token', '');
        Setting::set('bot_inventory_token', '');

        TelegramService::sendOrder($this->order());
        TelegramService::sendInventory('تنبيه');

        $this->assertTrue($this->sentTo(self::BASE), 'الفراغ يعود إلى الأساسي');
    }

    /** البوتان مستقلان: تعيين أحدهما لا يجرّ الآخر معه */
    public function test_the_two_bots_do_not_bleed_into_each_other(): void
    {
        Setting::set('bot_orders_token', self::ORDERS);
        Setting::set('bot_inventory_token', self::INVENTORY);

        TelegramService::sendInventory('تنبيه مخزون فقط');

        $this->assertTrue($this->sentTo(self::INVENTORY));
        $this->assertFalse($this->sentTo(self::ORDERS), 'تنبيه المخزون لا يخرج من بوت الطلبات');
    }
}
