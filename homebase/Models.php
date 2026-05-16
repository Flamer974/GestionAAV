<?php

/**
 * ============================================================
 * app/Models/User.php
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'avatar',
        'timezone', 'preferences', 'is_admin', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'preferences'       => 'array',
        'is_admin'          => 'boolean',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function chatSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function settings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    // ─── Accessors & Mutators ─────────────────────────────────

    /** Initiales pour l'avatar généré */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        return strtoupper(
            substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1)
        );
    }

    // ─── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }
}


/**
 * ============================================================
 * app/Models/Project.php
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'description', 'color',
        'icon', 'status', 'deadline', 'progress', 'meta',
    ];

    protected $casts = [
        'deadline' => 'date',
        'meta'     => 'array',
        'progress' => 'integer',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ─── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOverdue($query)
    {
        return $query->where('deadline', '<', now())->where('status', '!=', 'completed');
    }
}


/**
 * ============================================================
 * app/Models/Task.php
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'project_id', 'title', 'description',
        'status', 'priority', 'due_date', 'is_completed',
        'completed_at', 'estimated_minutes', 'actual_minutes',
        'sort_order', 'tags', 'subtasks', 'ai_suggestion',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
        'tags'         => 'array',
        'subtasks'     => 'array',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // ─── Scopes ───────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_completed', false)
                     ->where('due_date', '<', today());
    }

    public function scopeForToday($query)
    {
        return $query->whereDate('due_date', today());
    }

    // ─── Accessors ───────────────────────────────────────────

    /** Couleur CSS selon la priorité (pour l'UI) */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'urgent' => 'red',
            'high'   => 'orange',
            'medium' => 'yellow',
            'low'    => 'green',
            default  => 'gray',
        };
    }
}


/**
 * ============================================================
 * app/Models/Note.php
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'content', 'content_html',
        'ai_summary', 'ai_enhanced', 'category', 'tags',
        'color', 'is_pinned', 'is_archived', 'ai_processed',
    ];

    protected $casts = [
        'tags'         => 'array',
        'is_pinned'    => 'boolean',
        'is_archived'  => 'boolean',
        'ai_processed' => 'boolean',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}


/**
 * ============================================================
 * app/Models/ChatSession.php
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'title', 'model', 'total_tokens'];

    protected $casts = ['total_tokens' => 'integer'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** Dernier message de la conversation (aperçu) */
    public function lastMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }
}


/**
 * ============================================================
 * app/Models/ChatMessage.php
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_session_id', 'user_id', 'role',
        'content', 'tokens_used', 'model', 'meta',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'meta'        => 'array',
    ];

    public function session(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
