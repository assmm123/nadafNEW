<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ختم التوثيق على الطلبات المعتمدة
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('stamped_at')->nullable()->after('status');
            $table->foreignId('stamped_by')->nullable()->after('stamped_at')->constrained('users')->nullOnDelete();
        });

        // تكلفة الشراء لحساب الأرباح
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_usd', 10, 2)->nullable()->after('price_usd');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost_usd', 10, 2)->nullable()->after('unit_price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stamped_by');
            $table->dropColumn('stamped_at');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_usd');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost_usd');
        });
    }
};
