<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إتمام الطلب بلا حساب.
 *
 * ── العلّة التي يحلّها هذا التهجير ──
 * `orders.user_id` كان **إلزاميًّا**، ومسار `/checkout` محميًّا بوسطاء الدخول.
 * فالعميل الذي لا يريد إنشاء حساب **لا يستطيع الشراء إطلاقًا** — وهو أكبر
 * عقبة أمام الطلب في متجر صغير.
 *
 * ── القرار: لا ننشئ حسابًا للضيف ──
 * الطريق الأسهل كان إنشاء صفّ في `users` لكل ضيف. ورُفض لسببين:
 *   ١. يلوّث جدول المستخدمين بحسابات لا يدخلها أحد.
 *   ٢. ويحجز رقم هاتفه: فإن أراد التسجيل لاحقًا رُفض لأن الرقم «مستخدم».
 * فيُخزَّن اسم الضيف ورقمه **على الطلب نفسه**، ويبقى `user_id` فارغًا.
 *
 * وترتيب الأعمدة في `after()` مقصود: بيانات الضيف بجوار `user_id` حيث يبحث
 * عنها القارئ، لا في آخر الجدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            $table->string('customer_name', 120)->nullable()->after('user_id');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
