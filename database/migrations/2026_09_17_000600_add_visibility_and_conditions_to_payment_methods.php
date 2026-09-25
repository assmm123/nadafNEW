<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحكّم المالك بما يراه العميل في وسائل الدفع.
 *
 *   • `hidden_fields` — أي حقول جاهزة تُخفى عن العميل (رقم الحساب · الآيبان …).
 *     قائمة سوداء لا بيضاء: الافتراض الظهور، والإخفاء استثناء صريح.
 *   • `conditions` — شروط خاصة تُكتب للعميل («الحد الأدنى ١٠٠$»، «للمقيمين خارج سوريا»).
 *   • `min_order_usd` — شرط يُنفَّذ فعلًا: تُخفى الوسيلة إن كان الطلب أقل منه.
 *
 * أما `extra_fields` فموجود مسبقًا؛ أُضيف له في النموذج حقل `value` و`visible`
 * فلا يحتاج تعديل عمود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->json('hidden_fields')->nullable()->after('extra_fields');
            $table->text('conditions')->nullable()->after('instructions');
            $table->decimal('min_order_usd', 10, 2)->nullable()->after('conditions');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['hidden_fields', 'conditions', 'min_order_usd']);
        });
    }
};
