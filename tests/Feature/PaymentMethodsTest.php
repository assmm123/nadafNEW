<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * نظام وسائل الدفع — التصنيف والتحكّم بما يراه العميل.
 *
 * القاعدة الحاكمة: **ما يدخله المالك يظهر للعميل مباشرة**، وما يُخفيه لا يصل
 * للقالب أصلًا. وهذه الاختبارات تحرس الطرفين.
 */
class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function method(array $attrs = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'type' => 'sham_cash',
            'name' => 'محفظة اختبار',
            'is_active' => true,
        ], $attrs));
    }

    // ═══════════════ التصنيف ═══════════════

    public function test_every_type_belongs_to_a_known_group(): void
    {
        foreach (PaymentMethod::TYPES as $key => $meta) {
            $this->assertArrayHasKey(
                $meta['group'],
                PaymentMethod::GROUPS,
                "النوع {$key} يشير إلى مجموعة غير معرَّفة: {$meta['group']}"
            );
        }
    }

    public function test_the_types_are_grouped_for_the_admin_select(): void
    {
        $grouped = PaymentMethod::groupedTypes();

        // المفتاح اسم عربي للمجموعة لا مفتاحها — وإلا ظهر للعميل «hand»
        $this->assertArrayHasKey('التسليم باليد', $grouped);
        $this->assertArrayHasKey('المحافظ المحلية', $grouped);
        $this->assertArrayHasKey('البنوك', $grouped);
        $this->assertArrayHasKey('شركات التحويل المحلية', $grouped);
        $this->assertArrayHasKey('التحويل الدولي والخليجي', $grouped);
        $this->assertArrayHasKey('العملات الرقمية', $grouped);

        $this->assertContains('western_union', array_keys($grouped['التحويل الدولي والخليجي']));
        $this->assertContains('alharam', array_keys($grouped['شركات التحويل المحلية']));
        $this->assertContains('usdt', array_keys($grouped['العملات الرقمية']));
        $this->assertContains('cash_on_delivery', array_keys($grouped['التسليم باليد']));
    }

    public function test_a_method_reports_its_group_label_and_icon(): void
    {
        $method = $this->method(['type' => 'western_union']);

        $this->assertSame('intl_transfer', $method->group());
        $this->assertSame('التحويل الدولي والخليجي', $method->groupLabel());
        $this->assertSame('globe', $method->groupIcon());
    }

    public function test_legacy_types_still_work(): void
    {
        // البيانات القائمة تستخدم هذه الأنواع — يجب ألّا تنكسر
        foreach (['sham_cash', 'bank', 'wallet', 'cash_on_delivery', 'other'] as $legacy) {
            $this->assertArrayHasKey($legacy, PaymentMethod::TYPES, "النوع القديم {$legacy} مفقود");
        }
    }

    // ═══════════════ ما يراه العميل ═══════════════

    public function test_every_field_shows_by_default(): void
    {
        $method = $this->method();

        foreach (array_keys(PaymentMethod::HIDEABLE_FIELDS) as $field) {
            $this->assertTrue($method->showsField($field), "الحقل {$field} يجب أن يظهر افتراضيًا");
        }
    }

    public function test_the_admin_can_hide_any_field(): void
    {
        $method = $this->method(['hidden_fields' => ['iban', 'barcode']]);

        $this->assertFalse($method->showsField('iban'));
        $this->assertFalse($method->showsField('barcode'));
        // وما لم يُذكر يبقى ظاهرًا
        $this->assertTrue($method->showsField('account_number'));
        $this->assertTrue($method->showsField('instructions'));
    }

    public function test_hidden_fields_never_reach_the_customer_payload(): void
    {
        $this->method([
            'account_number' => '0999 123 456',
            'iban' => 'SY0300111234567890',
            'conditions' => 'شروط سرّية',
            'hidden_fields' => ['iban', 'conditions'],
        ]);

        $modal = new \App\Livewire\CheckoutModal;
        $ref = new \ReflectionMethod($modal, 'loadMethods');
        $ref->setAccessible(true);
        $ref->invoke($modal);

        $payload = $modal->paymentMethods[0];

        $this->assertNull($payload['iban'], 'الآيبان المُخفي يجب ألّا يصل للقالب');
        $this->assertNull($payload['conditions'], 'الشروط المُخفاة يجب ألّا تصل للقالب');
        $this->assertSame('0999 123 456', $payload['account_number'], 'وما لم يُخفَ يبقى');
    }

    // ═══════════════ الحقول المخصّصة ═══════════════

    public function test_the_admin_can_add_custom_fields_with_values(): void
    {
        $method = $this->method([
            'extra_fields' => [
                ['label' => 'عنوان الفرع', 'value' => 'المزة — شارع الجلاء', 'type' => 'text', 'visible' => true],
            ],
        ]);

        $fields = $method->visibleCustomFields();

        $this->assertCount(1, $fields);
        $this->assertSame('عنوان الفرع', $fields[0]['label']);
        $this->assertSame('المزة — شارع الجلاء', $fields[0]['value']);
    }

    public function test_a_custom_field_can_be_hidden(): void
    {
        $method = $this->method([
            'extra_fields' => [
                ['label' => 'ظاهر', 'value' => 'أ', 'visible' => true],
                ['label' => 'مخفي', 'value' => 'ب', 'visible' => false],
            ],
        ]);

        $labels = array_column($method->visibleCustomFields(), 'label');

        $this->assertContains('ظاهر', $labels);
        $this->assertNotContains('مخفي', $labels);
    }

    public function test_an_empty_custom_field_is_ignored(): void
    {
        $method = $this->method([
            'extra_fields' => [
                ['label' => '', 'value' => 'بلا عنوان'],
                ['label' => 'سليم', 'value' => 'قيمة'],
            ],
        ]);

        $this->assertCount(1, $method->visibleCustomFields());
    }

    // ═══════════════ الشروط ═══════════════

    public function test_a_method_without_a_minimum_is_always_available(): void
    {
        $this->assertTrue($this->method()->isAvailableFor(1.0));
        $this->assertTrue($this->method()->isAvailableFor(0.0));
    }

    public function test_a_minimum_order_hides_the_method_below_it(): void
    {
        $method = $this->method(['min_order_usd' => 100]);

        $this->assertFalse($method->isAvailableFor(99.99), 'أقل من الحد ⇒ غير متاحة');
        $this->assertTrue($method->isAvailableFor(100.0), 'عند الحد بالضبط ⇒ متاحة');
        $this->assertTrue($method->isAvailableFor(150.0));
    }

    // ═══════════════ اللوحة ═══════════════

    public function test_the_admin_page_renders_with_the_new_sections(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Livewire::test(ManagePaymentMethods::class)
            ->mountAction('create')
            ->html();

        $this->assertStringContainsString('ما يراه العميل', $html);
        $this->assertStringContainsString('حقول مخصّصة', $html);
        $this->assertStringContainsString('أضف حقلًا', $html);
        $this->assertStringContainsString('الحد الأدنى لقيمة الطلب', $html);
        $this->assertStringContainsString('شروط خاصة تظهر للعميل', $html);
    }

    public function test_the_admin_can_create_a_method_with_visibility_and_conditions(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManagePaymentMethods::class)
            ->callAction('create', data: [
                'type' => 'western_union',
                'name' => 'ويسترن يونيون — فرع المزة',
                'account_name' => 'متجر نداف',
                'conditions' => 'الحوالة باسم المتجر فقط',
                'min_order_usd' => 50,
                'hidden_fields' => ['iban'],
                'is_active' => true,
                'requires_proof' => true,
            ])
            ->assertHasNoActionErrors();

        $method = PaymentMethod::where('name', 'ويسترن يونيون — فرع المزة')->first();

        $this->assertNotNull($method);
        $this->assertSame('intl_transfer', $method->group());
        $this->assertSame('الحوالة باسم المتجر فقط', $method->conditions);
        $this->assertFalse($method->showsField('iban'));
        $this->assertFalse($method->isAvailableFor(49));
        $this->assertTrue($method->isAvailableFor(50));
    }

    public function test_only_active_methods_reach_the_customer(): void
    {
        $this->method(['name' => 'ظاهرة', 'is_active' => true]);
        $this->method(['name' => 'معطّلة', 'is_active' => false, 'type' => 'bank']);

        $modal = new \App\Livewire\CheckoutModal;
        $ref = new \ReflectionMethod($modal, 'loadMethods');
        $ref->setAccessible(true);
        $ref->invoke($modal);

        $names = array_column($modal->paymentMethods, 'name');

        $this->assertContains('ظاهرة', $names);
        $this->assertNotContains('معطّلة', $names);
    }
}
