<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دعم التضمين المباشر لفيديو يوتيوب في السلايدر.
 *
 * الدافع عملي بحت: الاستضافات المجانية تحدّ الرفع عند ~10 ميغا، ففيديو
 * الدقيقة الواحدة لا يصعد إلى الخادم أصلًا. وخدمات الفيديو مضطردة على
 * تقديمه مجانًا. فبدل أن تُرفع الشريحة بصورة ثابتة + رابط يفتح يوتيوب
 * خارجًا، صار الفيديو يُضمَّن داخل السلايدر ويُخدَّم من خوادم يوتيوب —
 * لا مساحة من موقعنا ولا حدّ رفع.
 *
 * والعمود مستقل عن video_path عمدًا: ذاك ملف مرفوع، وهذا رابط نصّي.
 * والنموذج هو من يقرر أيّهما يعرض (انظر Slide::youtubeEmbedUrl).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            // رابط يوتيوب كامل أو معرف الفيديو — يُخزَّن كما كتبه المالك
            // ويُستخرج منه المعرف عند العرض (انظر Slide::extractYoutubeId).
            $table->string('youtube_url')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->dropColumn('youtube_url');
        });
    }
};
