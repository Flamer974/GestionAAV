<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
        'tags' => 'array',
        'subtasks' => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function scopePending($query) { return $query->where('is_completed', false); }
    public function scopeUrgent($query) { return $query->whereIn('priority', ['high', 'urgent']); }
    public function scopeOverdue($query) {
        return $query->where('is_completed', false)->where('due_date', '<', today());
    }
    public function scopeForToday($query) { return $query->whereDate('due_date', today()); }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }
}