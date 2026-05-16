<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'meta' => 'array',
        'progress' => 'integer',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeOverdue($query) {
        return $query->where('deadline', '<', now())->where('status', '!=', 'completed');
    }
}