<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'avatar_url', 'theme',
        'preferences', 'is_admin', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'preferences' => 'array',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Relations
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
    public function notes(): HasMany { return $this->hasMany(Note::class); }
    public function chatSessions(): HasMany { return $this->hasMany(ChatSession::class); }
    public function settings(): HasOne { return $this->hasOne(UserSetting::class); }

    // Accessor
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        return strtoupper(
            substr($words[0] ?? '', 0, 1) . 
            substr($words[1] ?? '', 0, 1)
        );
    }

    // Scopes
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeAdmins($query) { return $query->where('is_admin', true); }
}