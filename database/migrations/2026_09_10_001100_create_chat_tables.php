<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أسئلة وأجوبة الشات العائم — يديرها الأدمن، ويظهر للعميل أجوبة جاهزة 100% (بلا ذكاء اصطناعي)
        Schema::create('chat_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question', 200);
            $table->text('answer');
            $table->json('keywords')->nullable(); // كلمات مفتاحية للمطابقة
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // سجل محادثات الزوار — يظهر في قسم الشات بالأدمن
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 80)->index();
            $table->string('visitor_name', 100)->nullable();
            $table->text('message');
            $table->text('matched_answer')->nullable();
            $table->boolean('was_helpful')->nullable(); // رد آلي مطابق أم لا
            $table->string('trigger', 40)->default('user'); // user | payment_error
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
        Schema::dropIfExists('chat_questions');
    }
};
