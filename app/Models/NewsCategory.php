<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends Model
{
    protected $table = 'news_categories';

    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'is_active',
        'ordering',
        'language',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get news articles in this category.
     */
    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }
}
