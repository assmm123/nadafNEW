<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setMany([
            // بيانات المتجر
            'store_name_ar' => 'نداف',
            'store_name_en' => 'NADAF',
            'store_phone' => '+963 999 123 456',

            // العملة
            'exchange_rate' => '15000',

            // الشحن
            'shipping_enabled' => '1',
            'shipping_fee_usd' => '2',

            // الجملة
            'wholesale_min_quantity' => '10',
            'wholesale_min_amount_usd' => '200',

            // حدود السلة — أقصى كمية للعنصر الواحد في المنتجات بلا متغيرات
            'max_qty_per_item' => '99',

            // ── مرحلة قبض الدفع ──
            // on_confirm | on_ship | on_deliver | optional
            'payment_collect_stage' => 'on_deliver',

            // ── الصفحة الرئيسية ──
            // ترتيب الأقسام (JSON) — يُدار من اللوحة: الصفحة الرئيسية ← ترتيب الأقسام
            'home_sections' => '["hero","categories","latest","trust"]',
            'home_hero_height' => '56',

            // تعريف المنصة (العمود المجاور للسلايدر)
            'about_kicker' => 'عن نداف',
            'about_title' => 'لا نبيع كرافات',
            'about_title_accent' => 'نبيع التفاصيل',
            'about_body' => 'نختار كل قطعة يدويًا: حرير مستورد، حاشية مخيطة، وألوان لا تنافس الطقم. نُشحن إلى كل المحافظات، ومع كل طلب فاتورة نظامية.',
            'about_stat_1_value' => '73',
            'about_stat_1_label' => 'قطعة معروضة',
            'about_stat_2_value' => '8',
            'about_stat_2_label' => 'سنوات خبرة',
            'about_stat_3_value' => '14',
            'about_stat_3_label' => 'محافظة توصيل',
            'about_cta_1_label' => 'تسوّق الآن',
            'about_cta_1_link' => '#categories',

            // شريط الثقة
            'trust_1_title' => 'توصيل لكل المحافظات',
            'trust_1_text' => 'دمشق وريفها خلال ٢٤ ساعة، وبقية المحافظات ٢–٤ أيام.',
            'trust_2_title' => 'دفع موثّق',
            'trust_2_text' => 'شام كاش أو حوالة بنكية، ومع كل طلب فاتورة نظامية.',
            'trust_3_title' => 'استبدال خلال ٣ أيام',
            'trust_3_text' => 'لم يناسب المقاس أو اللون؟ نستبدله بلا أسئلة.',
            'trust_4_title' => 'سعر جملة من ١٠ قطع',
            'trust_4_text' => 'يُطبَّق تلقائيًا عند بلوغ الكمية أو قيمة الطلب.',

            // الاستفسار
            'inquiry_enabled_global' => '1',

            // ختم التوثيق
            'stamp_top_text' => 'متجر نداف — نداف',

            // الإشعارات
            'notify_telegram_enabled' => '0',
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
            'notify_email_enabled' => '0',
            'notify_email' => '',
        ]);
    }
}
