<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->longText('content_html')->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('ai_enhanced')->nullable();
            $table->string('category', 50)->nullable();
            $table->json('tags')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('ai_processed')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'is_archived', 'is_pinned']);
            $table->index(['user_id', 'category']);
            $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};