<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevelopmentTimelineItem extends Model
{
    protected $fillable = [
        'title',
        'icon',
        'value',
        'value_index',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope a query to only active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
