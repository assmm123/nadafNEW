<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `name_en` للقسم صار اختياريًا فعليًا.
 *
 * نفس خطأ المنتجات: النموذج يقول «اختياري» والعمود `NOT NULL`، فيسقط الحفظ بـ
 *     NOT NULL constraint failed: categories.name_en
 * والكود يشتقّ الرابط من الاسم العربي أصلًا
 * (`uniqueSlug($category->name_en ?: $category->name_ar)`)، فالتصحيح في العمود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en')->nullable(false)->change();
        });
    }
};
