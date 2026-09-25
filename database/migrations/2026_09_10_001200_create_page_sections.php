<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أقسام «من نحن» الديناميكية — نص/صور/فيديو/معرض لكل قسم
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('heading_ar', 150)->nullable();
            $table->string('heading_en', 150)->nullable();
            $table->text('body_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->json('images')->nullable(); // مصفوفة مسارات صور
            $table->json('videos')->nullable(); // مصفوفة مسارات فيديو (كل ≤10 ثوانٍ)
            $table->string('layout', 20)->default('text'); // text | gallery | video | split
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
