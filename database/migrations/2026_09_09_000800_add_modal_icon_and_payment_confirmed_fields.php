<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أيقونة مرفوعة للمحفظة (تظهر في نافذة شراء الآن)
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('icon_path')->nullable()->after('type');
        });

        // قبض المال: يُثبَّت تلقائيًا عند اعتماد إثبات الدفع → الختم الأخضر
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_confirmed_at')->nullable()->after('stamped_by');
            $table->foreignId('payment_confirmed_by')->nullable()->after('payment_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_confirmed_by');
            $table->dropColumn('payment_confirmed_at');
        });
    }
};
