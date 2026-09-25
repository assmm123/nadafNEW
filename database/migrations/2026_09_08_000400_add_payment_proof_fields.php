<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إثبات الدفع على الطلبات
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('payment_method_id');
            $table->string('payment_reference')->nullable()->after('payment_proof_path');
            $table->string('payment_sender_name')->nullable()->after('payment_reference');
        });

        // هل تتطلب الوسيلة إثبات دفع قبل توليد كود الطلب؟
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('requires_proof')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_proof_path', 'payment_reference', 'payment_sender_name']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('requires_proof');
        });
    }
};
