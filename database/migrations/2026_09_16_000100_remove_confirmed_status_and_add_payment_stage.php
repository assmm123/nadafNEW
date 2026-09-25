<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حذف حالة «مؤكد» + إضافة إعداد مرحلة القبض.
 *
 * «مؤكد» لم تبقَ حالة: القبض صار علامة مستقلة (الختم الأخضر) لا مرحلة.
 * والطلبات المؤكدة القائمة تُنقل إلى «قيد التحضير» — المرحلة التي كانت
 * تليها فعليًا — فتبقى في المسار الصحيح بلا فقدان أي بيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'confirmed')
            ->update(['status' => 'preparing']);

        // مرحلة اشتراط قبض المال: on_confirm | on_ship | on_deliver | optional
        if (! Setting::get('payment_collect_stage')) {
            Setting::set('payment_collect_stage', 'on_deliver');
        }
    }

    public function down(): void
    {
        Setting::set('payment_collect_stage', '');
    }
};
