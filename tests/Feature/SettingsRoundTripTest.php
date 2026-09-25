<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * سلامة الإعدادات عند الحفظ.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * `save()` يمرّ على **كل** حقول النموذج ويكتبها في جدول `settings`. فإن كان
 * حقلٌ لا تُحمَّل قيمته في `mount()` — باسم مختلف حرفًا واحدًا مثلًا — ظهر
 * فارغًا، وكتابة الفراغ تمحو القيمة المحفوظة. **فقدان بيانات صامت**: يكفي أن
 * يفتح المالك الإعدادات ويضغط حفظ بلا أن يغيّر شيئًا.
 *
 * وهذا ما حدث فعلًا: مفتاح `telegram_command_bot_token` كان مكتوبًا في
 * `mount()` باسم `bot_command_token`، فكان توكن البوت التفاعلي يُمحى عند كل حفظ.
 *
 * فالاختبار لا يفحص حقلًا بعينه: **يفتح الإعدادات، يحفظ بلا تغيير، ثم يتأكد
 * أن كل القيم كما كانت**. فأي حقل يُضاف لاحقًا بهذا الخلل يسقط هنا فورًا.
 */
class SettingsRoundTripTest extends TestCase
{
    use RefreshDatabase;

    /** قيم تشمل كل الأنواع: توكنات · مسارات · أرقام · نصوص عربية */
    private const STORED = [
        'store_name_ar' => 'متجر نداف',
        'store_phone' => '+963930322406',
        'whatsapp_number' => '00963930322406',
        'wa_country_code' => '963',
        'exchange_rate' => '15000',
        'telegram_bot_token' => '111111:notify-token',
        'bot_orders_token' => '222222:orders-token',
        'bot_inventory_token' => '333333:inventory-token',
        'telegram_command_bot_token' => '444444:command-token',
        'telegram_chat_id' => '8388664821',
        'telegram_admin_chat_id' => '8388664821',
        'logo_path' => 'branding/logo.png',
        'stamp_green_path' => 'branding/green.png',
        'stamp_gold_path' => 'branding/gold.png',
    ];

    public function test_saving_the_settings_page_without_changes_keeps_every_value(): void
    {
        // حقول الرفع تُتحقَّق من وجود الملف على القرص: ملف مفقود يُسقط الحقل
        // من حالة النموذج فيُمحى الإعداد. فالنُّصب هنا يحاكي الحالة السليمة.
        Storage::fake('public');
        foreach (['branding/logo.png', 'branding/green.png', 'branding/gold.png'] as $file) {
            Storage::disk('public')->put($file, 'x');
        }

        foreach (self::STORED as $key => $value) {
            Setting::set($key, $value);
        }

        $this->actingAs(User::factory()->create(['role' => 'owner']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Settings::class)
            ->assertOk()
            ->call('save')
            ->assertHasNoFormErrors();

        foreach (self::STORED as $key => $value) {
            $this->assertSame(
                $value,
                Setting::get($key),
                "الإعداد «{$key}» مُحي أو تغيّر بمجرد الحفظ بلا تعديل",
            );
        }
    }

    /**
     * وكل حقل في النموذج لا بد أن تُحمَّل قيمته في `mount()` — وإلا ظهر فارغًا
     * ومحا ما قبله عند الحفظ. هذا الاختبار يقيس **الاقتران** لا النتيجة،
     * فيكشف الحقل الجديد الذي يُضاف بنسيان تحميله.
     */
    public function test_every_form_field_is_also_loaded_on_mount(): void
    {
        $source = file_get_contents(app_path('Filament/Pages/Settings.php'));

        preg_match_all("/Components\\\\[A-Za-z]+::make\\('([a-z0-9_]+)'\\)/", $source, $f);
        preg_match_all("/'([a-z0-9_]+)' => Setting::/", $source, $m);

        $fields = array_unique($f[1]);
        $mounted = array_unique($m[1]);

        // مستثنى صراحةً لا استنتاجًا:
        //   section       — حقل داخل الـRepeater لا يُخزَّن في settings
        //   home_sections — يُبنى من HomeSections::active() لا من Setting::get
        $exempt = ['section', 'home_sections'];

        $missing = array_values(array_diff($fields, $mounted, $exempt));

        $this->assertSame(
            [],
            $missing,
            "حقول تُعرض في الإعدادات ولا تُحمَّل قيمتها ⇒ تُكتب فارغة عند الحفظ:\n- ".implode("\n- ", $missing),
        );
    }
}
