<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accessgroup extends Model
{
    use HasFactory;

    protected $primaryKey = 'username';
    public $incrementing = false;

    protected $fillable = [
        'username',
        'field_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function field()
    {
        return $this->belongsTo(Field::class, 'field_id', 'id');
    }
}
