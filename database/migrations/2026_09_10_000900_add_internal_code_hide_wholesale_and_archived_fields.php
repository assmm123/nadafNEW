<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('internal_code', 60)->nullable()->after('slug');
            $table->boolean('hide_wholesale')->default(false)->after('wholesale_price_usd');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('payment_confirmed_by');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['internal_code', 'hide_wholesale']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
