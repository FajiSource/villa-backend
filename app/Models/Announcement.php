<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'image',
        'published_at',
        'expires_at',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Scope to get active announcements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Scope to get announcements ordered by priority
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc')
            ->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc');
    }
}

