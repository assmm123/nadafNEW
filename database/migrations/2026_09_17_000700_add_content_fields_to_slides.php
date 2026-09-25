<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محتوى أغنى للشريحة.
 *
 *   • `subtitle_ar/en` — سطر توضيحي تحت العنوان (كان العنوان وحده)
 *   • `button_text_ar/en` — نص الزر. كان مكتوبًا في الكود «تسوّق الآن»
 *     فلا يستطيع المالك تغييره لكل شريحة.
 *   • `duration_seconds` — مدة بقاء الشريحة. كانت ثابتة ٦ ثوانٍ في Alpine.
 *
 * وصورة الغلاف (`image_path`) موجودة أصلًا وتُستخدم كـposter للفيديو؛
 * الجديد أن النموذج صار يطلبها ويوضّح دورها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->string('subtitle_ar')->nullable()->after('title_en');
            $table->string('subtitle_en')->nullable()->after('subtitle_ar');
            $table->string('button_text_ar', 60)->nullable()->after('link');
            $table->string('button_text_en', 60)->nullable()->after('button_text_ar');
            $table->unsignedSmallInteger('duration_seconds')->default(6)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->dropColumn(['subtitle_ar', 'subtitle_en', 'button_text_ar', 'button_text_en', 'duration_seconds']);
        });
    }
};
