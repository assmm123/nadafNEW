<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `name_en` صار اختياريًا فعليًا.
 *
 * النموذج يقول «اختياري» لكن العمود `NOT NULL` في قاعدة البيانات — فأي منتج
 * يُحفظ بلا اسم إنجليزي كان يُسقط الطلب بخطأ قاعدة بيانات:
 *     NOT NULL constraint failed: products.name_en
 *
 * والكود يتعامل مع الفراغ أصلًا (`$this->name_en ?: $this->name_ar`)،
 * فالتصحيح في العمود لا في النموذج.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en')->nullable(false)->change();
        });
    }
};
