<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جداول البوت التفاعلي — قائمة الانتظار والجلسات
        Schema::create('bot_pending_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('field', 30); // quantity | price
            $table->string('new_value', 50);
            $table->unsignedBigInteger('chat_id');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id')->unique();
            $table->string('state', 30)->default('idle'); // idle | awaiting_edit_value
            $table->json('context')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // السلات المتروكة — لقطة عند ترك السلة بها محتوى دون إتمام الشراء
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('identifier', 120)->nullable(); // بريد أو هاتف للتواصل إن توفر
            $table->json('items');
            $table->decimal('total_usd', 10, 2)->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_activity_at')->index()->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamps();
        });

        // سجل استلام رسائل البوت (لمنع تكرار المعالجة ومراجعة الأدمن)
        Schema::create('bot_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->unsignedBigInteger('chat_id')->index();
            $table->string('username', 100)->nullable();
            $table->text('text')->nullable();
            $table->text('reply')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_message_logs');
        Schema::dropIfExists('abandoned_carts');
        Schema::dropIfExists('bot_sessions');
        Schema::dropIfExists('bot_pending_edits');
    }
};
