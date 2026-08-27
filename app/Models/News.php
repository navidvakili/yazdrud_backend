<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class News extends Model
{
    protected $fillable = [
        'title',
        'summary',
        'content',
        'category_id',
        'category_ids',
        'author_username',
        'author_name',
        'author_role',
        'image_url',
        'views_count',
        'likes_count',
        'is_pinned',
        'comments_enabled',
        'comments_mode',
        'is_photo_report',
        'photo_report_images',
        'status',
        'target_audience',
        'tags',
        'attachments',
        'published_at',
        'language',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'is_pinned' => 'boolean',
        'comments_enabled' => 'boolean',
        'comments_mode' => 'string',
        'is_photo_report' => 'boolean',
        'photo_report_images' => 'array',
        'category_ids' => 'array',
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

    /**
     * Get all comments for this news.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(NewsComment::class, 'news_id');
    }

    /**
     * Get only approved comments for this news.
     */
    public function approvedComments(): HasMany
    {
        return $this->hasMany(NewsComment::class, 'news_id')->where('is_approved', true);
    }
}
