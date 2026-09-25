<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Filament\Pages\StockCount;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * قسمان في لوحة الأدمن: «السجلات والأرباح» و«الجرد الفعلي».
 *
 * كل اختبار هنا يقابل عطلًا حقيقيًا وُجد في القسمين، لا حالة نظرية:
 *   • صفحة التقارير كانت ترسم بلا مشاكل لكن **بياناتها لا تُرى**: الرسم
 *     البياني اليومي فارغ لأن أعمدةه تعتمد أصنافًا غير مُولَّدة في بناء اللوحة.
 *   • الجرد كان يحفظ في حلقة بلا معاملة، ويكتب العدّ في صنف آخر عند الترشيح.
 */
class AdminReportsStockCountTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function variant(int $quantity, string $name = 'كرافتة'): ProductVariant
    {
        $category = Category::firstOrCreate(
            ['slug' => 'ties'],
            ['name_ar' => 'كرفات', 'name_en' => 'Ties'],
        );

        $product = Product::create([
            'category_id' => $category->id,
            'name_ar' => $name,
            'name_en' => 'Tie',
            'slug' => 'tie-'.uniqid(),
            'price_usd' => 10,
            'cost_usd' => 6,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }

    private function stockPage()
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::test(StockCount::class);
    }

    // ═══════════════ الجرد الفعلي ═══════════════

    public function test_the_stock_page_loads_with_the_quantity_prefilled_from_the_ledger(): void
    {
        $variant = $this->variant(17);

        $page = $this->stockPage();

        $this->assertSame(17, $page->get('counts')[0]['book']);
        $this->assertSame('17', $page->get('counts')[0]['actual'],
            'العدّ الفعلي يبدأ من الرصيد الدفتري فلا يُدخل المالك ما لم يغيّره');
        $this->assertSame($variant->id, $page->get('counts')[0]['id']);
    }

    public function test_the_summary_counts_surplus_shortage_and_net(): void
    {
        $this->variant(10, 'أ');
        $this->variant(10, 'ب');
        $this->variant(10, 'ج');

        $page = $this->stockPage();
        $page->set('counts.0.actual', '13');   // +3
        $page->set('counts.1.actual', '7');    // −3
        // الثالث مطابق

        $stats = $page->instance()->stats();

        $this->assertSame(3, $stats['rows']);
        $this->assertSame(2, $stats['counted']);
        $this->assertSame(1, $stats['untouched']);
        $this->assertSame(3, $stats['surplus']);
        $this->assertSame(3, $stats['shortage']);
        $this->assertSame(0, $stats['net'], 'زيادة ٣ ونقص ٣ ⇒ صافٍ صفر');
    }

    /**
     * العطل الذي يمنعه هذا الاختبار: `visibleCounts()` تُعيد قائمة **مفلترة**،
     * وحقل الإدخال مربوط بـ`counts.{i}.actual`. فلو استُخدم فهرس العرض بدل
     * الفهرس الأصلي لانزاح الربط عند أي ترشيح، فيُكتب العدّ في صنف آخر —
     * بلا أي خطأ ظاهر، وهو أسوأ من الفشل الصريح.
     */
    public function test_filtering_does_not_shift_the_row_binding(): void
    {
        $this->variant(10, 'أ');
        $this->variant(10, 'ب');
        $this->variant(10, 'ج');

        $page = $this->stockPage();
        $page->set('counts.0.actual', '10');   // مطابق
        $page->set('counts.1.actual', '14');   // فرق +4
        $page->set('counts.2.actual', '10');   // مطابق

        $page->set('filter', 'diff');

        $visible = $page->instance()->visibleCounts();

        $this->assertCount(1, $visible, 'صفّ واحد فقط فيه فرق');
        $this->assertSame(1, $visible[0]['i'], 'فهرسه في المصفوفة الأصلية لا في القائمة المفلترة');
        $this->assertSame('14', $visible[0]['actual']);

        // والقيمة المكتوبة تُحفظ في الصنف الصحيح
        $page->call('apply');

        $this->assertSame(14, ProductVariant::orderBy('id')->get()[1]->quantity);
        $this->assertSame(10, ProductVariant::orderBy('id')->get()[0]->quantity);
    }

    public function test_apply_records_only_the_rows_that_differ(): void
    {
        $this->variant(10, 'أ');
        $this->variant(20, 'ب');

        $page = $this->stockPage();
        $page->set('counts.0.actual', '10');   // مطابق ⇒ لا حركة
        $page->set('counts.1.actual', '24');   // +4
        $page->call('apply');

        $this->assertSame(10, ProductVariant::orderBy('id')->first()->quantity);
        $this->assertSame(24, ProductVariant::orderBy('id')->skip(1)->first()->quantity);

        $this->assertSame(1, \App\Models\StockMovement::where('type', 'adjust')->count(),
            'الصنف المطابق لا يُسجَّل له أي حركة');
    }

    /**
     * العطل الذي يمنعه: الحلقة كانت بلا معاملة خارجية. `StockService::record`
     * يفتح معاملة **لكل صنف على حدة**، فلو فشل الصنف الثاني بعد نجاح الأول
     * لبقي المخزون نصف مسوّى بلا أن يعلم أحد.
     */
    public function test_apply_rolls_back_everything_when_one_row_fails(): void
    {
        $good = $this->variant(10, 'سليم');

        $page = $this->stockPage();

        // صفّ بمعرّف غير موجود ⇒ StockService::record يرمي عند `firstOrFail`
        $page->set('counts', [
            ['id' => $good->id, 'name' => 'سليم', 'sku' => 'A', 'book' => 10, 'actual' => '4'],
            ['id' => 999999, 'name' => 'وهمي', 'sku' => 'X', 'book' => 10, 'actual' => '4'],
        ]);

        try {
            $page->call('apply');
        } catch (\Throwable) {
            // الفشل متوقّع — المهمّ ما تركته الحلقة بعده
        }

        $this->assertSame(10, $good->fresh()->quantity,
            'الصنف الأول يجب أن يعود كما كان — لا نصف تسوية');
        $this->assertSame(0, \App\Models\StockMovement::where('type', 'adjust')->count());
    }

    public function test_a_row_and_all_rows_can_be_returned_to_the_ledger_value(): void
    {
        $this->variant(10, 'أ');
        $this->variant(20, 'ب');

        $page = $this->stockPage();
        $page->set('counts.0.actual', '3');
        $page->set('counts.1.actual', '9');

        $id = $page->get('counts')[0]['id'];
        $page->call('resetRow', $id);

        $this->assertSame('10', $page->get('counts')[0]['actual'], 'أُعيد إلى الدفتري');
        $this->assertSame('9', $page->get('counts')[1]['actual'], 'والآخر لم يُمسّ');

        $page->call('resetAll');

        $this->assertSame('10', $page->get('counts')[0]['actual']);
        $this->assertSame('20', $page->get('counts')[1]['actual']);
        $this->assertSame(0, $page->instance()->stats()['counted'], 'لا فروقات بعد الإرجاع');
    }

    public function test_search_matches_the_sku_as_well_as_the_name(): void
    {
        $a = $this->variant(5, 'كرافتة حرير');
        $b = $this->variant(5, 'طقم رسمي');
        $b->update(['sku' => 'LEADS-BRN-L']);

        $page = $this->stockPage();
        $page->set('search', 'LEADS');

        $counts = $page->get('counts');

        $this->assertCount(1, $counts);
        $this->assertSame($b->id, $counts[0]['id'], 'البحث بالرمز يجد الصنف');
    }

    /**
     * العطل الذي يمنعه: البحث كان يعيد بناء القائمة من القاعدة فيمحو كل ما
     * أدخله المالك. فيكفي بحثٌ عابر — أو ضغطة على مرشّح — لتضيع ساعة جرد.
     */
    public function test_search_keeps_the_entered_counts(): void
    {
        $this->variant(10, 'كرافتة حرير');
        $this->variant(10, 'كرافتة صوف');

        $page = $this->stockPage();
        $page->set('counts.0.actual', '7');
        $page->set('counts.1.actual', '3');

        $page->set('search', 'حرير');

        $counts = $page->get('counts');

        $this->assertCount(1, $counts, 'البحث ضيّق القائمة');
        $this->assertSame('7', $counts[0]['actual'], 'والعدّ المُدخل محفوظ لا ممحوّ');
    }

    /** وتغيير المرشّح عرضٌ لا بيانات — فلا يمسّ الإدخالات إطلاقًا */
    public function test_changing_the_filter_does_not_wipe_the_entered_counts(): void
    {
        $this->variant(10, 'أ');
        $this->variant(10, 'ب');

        $page = $this->stockPage();
        $page->set('counts.0.actual', '6');
        $page->set('counts.1.actual', '12');

        $page->set('filter', 'diff');

        $this->assertSame('6', $page->get('counts')[0]['actual']);
        $this->assertSame('12', $page->get('counts')[1]['actual']);
        $this->assertSame(2, $page->instance()->stats()['counted']);
    }

    /**
     * الاقتطاع كان صامتًا: `limit(200)` بلا أي إشارة، فقد يظن المالك أنه
     * جرد كل المخزون بينما الباقي خارج الشاشة ولا يُسوَّى.
     */
    public function test_exceeding_the_row_ceiling_is_reported_not_hidden(): void
    {
        $product = Product::create([
            'category_id' => Category::create(['name_ar' => 'أ', 'name_en' => 'A', 'slug' => 'a'])->id,
            'name_ar' => 'منتج',
            'name_en' => 'P',
            'slug' => 'p',
            'price_usd' => 1,
        ]);

        $rows = [];
        for ($i = 1; $i <= StockCount::MAX_ROWS + 1; $i++) {
            $rows[] = ['product_id' => $product->id, 'quantity' => 1, 'sku' => 'SKU-'.$i];
        }
        ProductVariant::insert($rows);

        $page = $this->stockPage();

        $this->assertTrue($page->get('truncated'), 'بلوغ السقف يجب أن يُعلن');
        $this->assertCount(StockCount::MAX_ROWS, $page->get('counts'));
        $this->assertSame(StockCount::MAX_ROWS + 1, $page->get('totalVariants'));
    }

    public function test_export_streams_a_csv(): void
    {
        $this->variant(10, 'كرافتة');

        $page = $this->stockPage();
        $page->call('export')->assertFileDownloaded();
    }

    // ═══════════════ السجلات والأرباح ═══════════════

    public function test_the_reports_page_renders_with_its_comparison_block(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // مبيعات في الفترة الحالية وحدها ⇒ الفترة السابقة صفر ⇒ لا أساس للمقارنة
        $customer = User::factory()->create(['role' => 'customer']);
        \App\Models\Order::create([
            'user_id' => $customer->id,
            'order_code' => \App\Models\Order::generateCode(),
            'status' => 'pending',
            'subtotal_usd' => 50,
            'discount_usd' => 0,
            'shipping_usd' => 0,
            'total_usd' => 50,
            'exchange_rate' => 15000,
            'total_syp' => 750000,
        ]);

        Livewire::test(Reports::class)
            ->assertOk()
            ->assertSet('comparison.previous.salesUsd', 0.0)
            ->assertSee('الفترة السابقة', escape: false)
            ->assertSee('لا فترة سابقة للمقارنة', escape: false);
    }

    public function test_the_reports_page_renders_its_charts_and_tables(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Reports::class)
            ->assertSee('المبيعات والأرباح اليومية', escape: false)
            ->assertSee('توزيع الأقسام', escape: false)
            ->assertSee('السجل اليومي', escape: false)
            ->assertSee('أفضل المنتجات', escape: false);
    }

    public function test_the_period_and_top_count_are_configurable(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Reports::class)
            ->set('topLimit', 20)
            ->assertOk()
            ->assertSet('topLimit', 20);
    }

    public function test_the_delta_helpers_describe_direction(): void
    {
        $this->assertSame('+12.5%', Reports::deltaLabel(12.5));
        $this->assertSame('-3.0%', Reports::deltaLabel(-3.0));
        $this->assertSame('—', Reports::deltaLabel(null), 'لا أساس للمقارنة ⇒ شرطة لا رقم كاذب');

        $this->assertSame('up', Reports::deltaTone(5.0));
        $this->assertSame('down', Reports::deltaTone(-5.0));
        $this->assertSame('flat', Reports::deltaTone(0.0));
        $this->assertSame('flat', Reports::deltaTone(null));
    }

    /**
     * عطل حقيقي وقع مرّتين: التسلسل الحرفي لوسم إغلاق body داخل سكربت
     * الصفحة. Livewire يحقن سكربته عند أول وسم إغلاق في الردّ؛ فإذا كان
     * داخل سكربتنا وقع الحقن في وسطه، وأنهى وسمُ الإغلاق الخاص به سكربتنا،
     * فظهر باقي الكود نصًّا في الصفحة وتعطّل زرّا التصدير.
     *
     * الدليل القاطع هو **عدد** وسوم إغلاق الجسم: صفحة سليمة تحمل وسمًا
     * واحدًا؛ وحين وقع العطل صارت ثلاثة (وسم الصفحة + وسمان داخل الكود
     * المتسرّب). ولا يكشف هذا لا بقراءة القالب ولا باختبار يبحث عن وجود
     * عنصر — بل بعدّ الوسوم في الردّ نفسه.
     */
    public function test_the_reports_page_does_not_leak_script_markup(): void
    {
        $this->actingAs($this->owner());

        $html = $this->get('/admin/reports')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '</body>'),
            'وسم إغلاق الجسم تسرّب داخل سكربت الصفحة — سينهي سكربتنا مبكرًا');
        $this->assertSame(1, substr_count($html, '</html>'));
        $this->assertSame(
            substr_count($html, '<script'),
            substr_count($html, '</script>'),
            'عدد فواتح السكربتات لا يساوي عدد غلقاتها ⇒ سكربت مقطوع',
        );
    }

    /** وسم الإغلاق المهرَّب لا يجوز أن يُقرأ وسمًا — وهذا ما يمنع العطل أعلاه */
    public function test_the_export_script_escapes_its_closing_tags(): void
    {
        $this->actingAs($this->owner());

        $html = $this->get('/admin/reports')->assertOk()->getContent();

        $this->assertStringContainsString('<\/body>', $html, 'وسم الإغلاق يجب أن يكون مهرَّبًا');
        $this->assertStringContainsString('__nadReportExportBound', $html, 'حارس الربط المزدوج مفقود');
    }
}
