<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إثبات الدفع يصير الأصل لا الاستثناء.
 *
 * ── المشكلة التي يحلّها هذا التهجير ──
 * عمود `requires_proof` أُنشئ بـ`default(false)`، وكذلك مفتاحه في نموذج
 * اللوحة. فكل وسيلة دفع جديدة **تعفى من الإثبات صامتة** إن لم يتذكّر
 * المالك قلب المفتاح. ونتيجةً لذلك، في قاعدة البيانات الحيّة كانت وسيلتان
 * من وسائل التحويل (`wallet/syriatel` و`bank/رستم باد`) معفاة، فيُولَّد
 * كود الطلب بلا أي إثبات دفع.
 *
 * والافتراضي هنا مقصود: **الأمان هو الأصل، والإعفاء قرار واعٍ**. الوسيلة
 * الجديدة تطلب الإثبات حتى يُقال لها غير ذلك — لا العكس.
 *
 * والإعفاء المشروع الوحيد بطبيعة الحال هو «الدفع عند التسليم»: لا حوالة
 * تُرفق به أصلًا. وما سواه — محافظ، حوالات، شام كاش — لا يُثبت الدفع فيه
 * إلا برقم أو صورة.
 *
 * ملاحظة: `down()` تُعيد الافتراضي ولا تُعيد البيانات. إعادة البيانات إلى
 * `false` تعني إعادة فتح الثغرة، وهذا ليس «رجوعًا» بل إفسادًا مقصودًا.
 */
return new class extends Migration
{
    /** النوع الوحيد المعفى بطبيعته — لا حوالة تُرفق بالدفع عند التسليم */
    private const EXEMPT_TYPES = ['cash_on_delivery'];

    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('requires_proof')->default(true)->change();
        });

        // إصلاح الصفوف القائمة: كل وسيلة ليست دفعًا عند التسليم تطلب الإثبات.
        // ولو تُركت كما هي لبقيت البوابة تمرّرها — البوابة تقرأ هذا العمود.
        DB::table('payment_methods')
            ->whereNotIn('type', self::EXEMPT_TYPES)
            ->update(['requires_proof' => true]);
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('requires_proof')->default(false)->change();
        });
    }
};
