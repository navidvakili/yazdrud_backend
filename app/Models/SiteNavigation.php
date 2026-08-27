<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SiteNavigation extends Model
{
    use HasFactory;

    protected $fillable = [
        'language',
        'location',
        'slug',
        'name',
        'items',
        'status',
        'version',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'items'      => 'array',
        'version'    => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: active menus only (for public display).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: menus of a given language (resolved through Language::resolve).
     */
    public function scopeForLanguage($query, ?string $lang)
    {
        return $query->where('language', \App\Models\Language::resolve($lang));
    }
}
