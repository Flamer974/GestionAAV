<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('model', 50)->default('mistral-small-latest');
            $table->integer('total_tokens')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // user, assistant, system
            $table->text('content');
            $table->integer('tokens_used')->default(0);
            $table->string('model', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index(['chat_session_id', 'created_at']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};