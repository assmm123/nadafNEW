<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح تصنيف المنتجات القائمة.
 *
 * التهجير الأول وسم **كل** منتج له متغيرات بأنه `variant` — وهذا صحيح في
 * الأغلب لكنه ليس دقيقًا: منتج له **متغير واحد بلا لون ولا مقاس** هو في
 * الحقيقة «قطعة واحدة»، ولا معنى لأن يُعرض للعميل كمنتج بألوان.
 *
 * فالقاعدة الدقيقة: مكرر = منتج له أكثر من متغير، أو متغير واحد **وله لون
 * أو مقاس**. وما عدا ذلك فردي.
 */
return new class extends Migration
{
    public function up(): void
    {
        $single = DB::table('products')
            ->where('product_type', 'variant')
            ->whereIn('id', function ($query) {
                $query->select('product_id')
                    ->from('product_variants')
                    ->groupBy('product_id')
                    ->havingRaw('COUNT(*) = 1')
                    ->havingRaw("MAX(COALESCE(color, '')) = ''")
                    ->havingRaw("MAX(COALESCE(size, '')) = ''");
            })
            ->pluck('id');

        if ($single->isNotEmpty()) {
            DB::table('products')->whereIn('id', $single)->update(['product_type' => 'single']);
        }
    }

    public function down(): void
    {
        // لا رجوع: التصنيف تصحيح لا تغيير
    }
};
