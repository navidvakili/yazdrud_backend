<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountyProject extends Model
{
    protected $fillable = [
        'county_id',
        'county_name',
        'road_projects_count',
        'housing_units_count',
        'urban_plans_count',
        'road_progress',
        'housing_progress',
        'urban_progress',
        'has_active_road_project',
        'has_housing_workshop',
        'description',
        'is_active',
        'language',
    ];

    protected $casts = [
        'road_projects_count' => 'integer',
        'housing_units_count' => 'integer',
        'urban_plans_count' => 'integer',
        'road_progress' => 'integer',
        'housing_progress' => 'integer',
        'urban_progress' => 'integer',
        'has_active_road_project' => 'boolean',
        'has_housing_workshop' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the formatted Persian name for the county.
     */
    public function getFormattedNameAttribute(): string
    {
        return "شهرستان {$this->county_name}";
    }
}
