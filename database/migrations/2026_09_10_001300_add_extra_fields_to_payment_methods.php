<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // حقول مرنة يحددها الأدمن لكل وسيلة: [{key, label, type}] — type: text|barcode_image|proof_upload|none
            $table->json('extra_fields')->nullable()->after('barcode_path');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('extra_fields');
        });
    }
};
