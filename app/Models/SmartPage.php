<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmartPage extends Model
{
    protected $fillable = [
        'language',
        'translation_group',
        'title',
        'slug',
        'parent_id',
        'sort_order',
        'status',
        'seo',
        'schema',
        'author_username',
        'author_name',
        'author_role',
        'published_at',
    ];

    protected $casts = [
        'seo'          => 'array',
        'schema'       => 'array',
        'published_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /** صفحهٔ والد (اگر این صفحه زیرمجموعه باشد) */
    public function parent()
    {
        return $this->belongsTo(SmartPage::class, 'parent_id');
    }

    /** زیرصفحه‌های این صفحه (مرتب بر اساس sort_order) */
    public function children()
    {
        return $this->hasMany(SmartPage::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** آیا این صفحه زیرصفحه است؟ */
    public function getIsChildAttribute(): bool
    {
        return !is_null($this->parent_id);
    }
}
