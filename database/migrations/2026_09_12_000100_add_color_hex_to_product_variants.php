<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // درجة اللون الدقيقة (hex) — تُختار بالنقر من مربع اللون وتُعرض كدائرة للعميل
            $table->string('color_hex', 9)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('color_hex');
        });
    }
};
