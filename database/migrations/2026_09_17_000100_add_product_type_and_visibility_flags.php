<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نوع المنتج + أعلام الظهور للعميل.
 *
 * `product_type` يميّز بين:
 *   single  = قطعة واحدة (لون ومقاس واحد) — لا يختار العميل شيئًا
 *   variant = عدة نسخ من القطعة نفسها باختلاف اللون/المقاس، ولكل نسخة مخزونها
 *
 * وهي **سمة محفوظة لا مستنتجة**: اعتماد عدد المتغيرات وحده لا يفرّق بين
 * «منتج فردي لم يُضف متغيره بعد» و«منتج مكرر فُرّغت متغيراته».
 *
 * وأعلام الظهور تسمح بإخفاء تفصيل بعينه عن العميل **لكل منتج على حدة**،
 * بلا مساس بالإعدادات العامة (وهي تبقى الحاكم الأعلى).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 20)->default('single')->after('category_id');

            // إخفاء تفصيل عن العميل لهذا المنتج وحده
            $table->boolean('hide_price')->default(false)->after('old_price_usd');
            $table->boolean('hide_unit_price')->default(false)->after('hide_price');
            $table->boolean('hide_colors')->default(false)->after('hide_unit_price');
        });

        // المنتجات القائمة كلها لها متغيرات فعلية ⇒ مكررة.
        // ولا تُلمس `hide_wholesale` القائمة: تُعاد استخدامها كما هي.
        DB::table('products')
            ->whereIn('id', function ($query) {
                $query->select('product_id')->from('product_variants');
            })
            ->update(['product_type' => 'variant']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'hide_price', 'hide_unit_price', 'hide_colors']);
        });
    }
};
