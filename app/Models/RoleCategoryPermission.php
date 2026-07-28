<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleCategoryPermission extends Model
{
    protected $fillable = [
        'role_id',
        'category_type',
        'category_id',
        'permission',
    ];

    protected $casts = [
        'category_id' => 'integer',
    ];
}
