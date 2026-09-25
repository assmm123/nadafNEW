<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس على اسم المنتج.
 *
 * سبب وجوده: فحص «الاسم المتكرر» في نموذج المنتج يستعلم بالاسم عند مغادرة
 * الحقل، وبلا فهرس يصير مسحًا كاملًا للجدول في كل مرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('name_ar');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name_ar']);
        });
    }
};
