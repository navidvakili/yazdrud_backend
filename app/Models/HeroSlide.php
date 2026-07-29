<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HeroSlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'title',
        'subtitle',
        'badge',
        'badge_icon',
        'bg_image',
        'primary_cta_text',
        'primary_cta_target',
        'secondary_cta_text',
        'secondary_cta_target',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
