<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تفاصيل أدنى لوسائل الدفع + خيار تجاهل تحقق SSL
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('iban')->nullable()->after('account_number');
            $table->string('barcode_path')->nullable()->after('instructions');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('city')->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['iban', 'barcode_path']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
