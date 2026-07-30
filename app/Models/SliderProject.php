<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SliderProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'project_data',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'project_data' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: only active projects for public display.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
