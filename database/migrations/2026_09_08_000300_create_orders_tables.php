<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_code', 20)->unique();
            $table->string('status', 20)->default('pending'); // pending|confirmed|preparing|shipped|delivered|cancelled
            $table->decimal('subtotal_usd', 12, 2);
            $table->decimal('discount_usd', 12, 2)->default(0);
            $table->decimal('shipping_usd', 12, 2)->default(0);
            $table->decimal('total_usd', 12, 2);
            $table->decimal('exchange_rate', 15, 2); // سعر الصرف لحظة الطلب
            $table->decimal('total_syp', 15, 2);
            $table->string('shipping_method', 20)->default('pickup'); // pickup | local
            $table->text('shipping_address')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price_usd', 12, 2);
            $table->decimal('total_price_usd', 12, 2);
            $table->boolean('is_wholesale')->default(false);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
