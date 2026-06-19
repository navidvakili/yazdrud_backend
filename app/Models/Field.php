<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'cat_id',
        'college_id',
        'group_id',
        'field_id',
        'field_group_id',
        'title',
        'maghte',
        'active',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, Accessgroup::class, 'field_id', 'username', 'id', 'username');
    }

    public function college()
    {
        return $this->belongsTo(College::class, 'college_id', 'id');
    }
}
