<?php
/**
 * ============================================================
 * MIGRATIONS COMPLÈTES — HomeBase
 * ============================================================
 * Chemin de chaque fichier : database/migrations/
 * Commande : php artisan migrate
 * ============================================================
 */


// ============================================================
// FICHIER 1 : database/migrations/2024_01_01_000001_create_users_table.php
// ============================================================
/*
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();             // Photo de profil
            $table->string('timezone')->default('Indian/Reunion');
            $table->json('preferences')->nullable();          // Préférences UI
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
*/


// ============================================================
// FICHIER 2 : database/migrations/2024_01_01_000002_create_projects_table.php
// ============================================================
/*
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6366f1');  // Couleur hex pour UI
            $table->string('icon', 50)->default('folder');   // Icône Heroicon
            $table->enum('status', ['active', 'paused', 'completed', 'archived'])
                  ->default('active');
            $table->date('deadline')->nullable();
            $table->integer('progress')->default(0);          // 0-100%
            $table->json('meta')->nullable();                 // Métadonnées libres
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
*/


// ============================================================
// FICHIER 3 : database/migrations/2024_01_01_000003_create_tasks_table.php
// ============================================================
/*
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'review', 'done'])
                  ->default('todo');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                  ->default('medium');
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('estimated_minutes')->nullable();  // Estimation Pomodoro
            $table->integer('actual_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('tags')->nullable();                  // Tags libres
            $table->json('subtasks')->nullable();              // Sous-tâches JSON
            $table->text('ai_suggestion')->nullable();         // Suggestion IA
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'priority']);
            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
*/


// ============================================================
// FICHIER 4 : database/migrations/2024_01_01_000004_create_notes_table.php
// ============================================================
/*
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
            $table->longText('content')->nullable();           // Contenu brut
            $table->longText('content_html')->nullable();      // Contenu formaté
            $table->text('ai_summary')->nullable();            // Résumé IA
            $table->text('ai_enhanced')->nullable();           // Version améliorée IA
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->string('color', 7)->default('#fbbf24');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('ai_processed')->default(false);  // Flag traitement IA
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_pinned', 'is_archived']);
            $table->fullText(['title', 'content']);            // Recherche full-text
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
*/


// ============================================================
// FICHIER 5 : database/migrations/2024_01_01_000005_create_chat_histories_table.php
// ============================================================
/*
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations (sessions de chat)
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Nouvelle conversation');
            $table->string('model')->default('mistral-small-latest');
            $table->integer('total_tokens')->default(0);       // Suivi quota
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });

        // Messages individuels
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->integer('tokens_used')->default(0);
            $table->string('model')->nullable();
            $table->json('meta')->nullable();                  // Température, etc.
            $table->timestamps();

            $table->index(['chat_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
*/


// ============================================================
// FICHIER 6 : database/migrations/2024_01_01_000006_create_tools_table.php
// Outils du quotidien (convertisseur, minuteur, etc.)
// ============================================================
/*
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('theme')->default('light');         // light | dark | system
            $table->string('language')->default('fr');
            $table->string('dashboard_layout')->default('default');
            $table->json('widget_order')->nullable();
            $table->json('ai_preferences')->nullable();        // Préférences IA
            $table->integer('daily_ai_usage')->default(0);    // Compteur quotidien
            $table->date('ai_usage_reset_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
*/

echo "
╔══════════════════════════════════════════════════════════════╗
║  INSTRUCTIONS POUR LES MIGRATIONS                           ║
║                                                              ║
║  Créer chaque fichier dans database/migrations/              ║
║  avec le nom horodaté correct, par exemple :                ║
║                                                              ║
║  2024_01_01_000001_create_users_table.php                   ║
║  2024_01_01_000002_create_projects_table.php                ║
║  2024_01_01_000003_create_tasks_table.php                   ║
║  2024_01_01_000004_create_notes_table.php                   ║
║  2024_01_01_000005_create_chat_histories_table.php          ║
║  2024_01_01_000006_create_user_settings_table.php           ║
║                                                              ║
║  Puis exécuter : php artisan migrate                        ║
╚══════════════════════════════════════════════════════════════╝
";
