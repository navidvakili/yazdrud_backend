<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $fillable = [
        'title',
        'summary',
        'content',
        'category_id',
        'author_username',
        'author_name',
        'author_role',
        'image_url',
        'views_count',
        'likes_count',
        'is_pinned',
        'status',
        'target_audience',
        'tags',
        'attachments',
        'published_at',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'is_pinned' => 'boolean',
        'tags' => 'array',
        'attachments' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Get the category this news belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    /**
     * Get the author (user) of this news.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_username');
    }
}
