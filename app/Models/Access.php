<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent',
        'title',
        'url',
        'icon',
        'roles',
        'ordering',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'roles'  => 'array',
    ];
}
