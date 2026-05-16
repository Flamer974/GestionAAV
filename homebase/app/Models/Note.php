<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'tags' => 'array',
        'is_pinned' => 'boolean',
        'is_archived' => 'boolean',
        'ai_processed' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function scopeActive($query) { return $query->where('is_archived', false); }
    public function scopePinned($query) { return $query->where('is_pinned', true); }
}