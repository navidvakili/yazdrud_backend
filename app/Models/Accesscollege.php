<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accesscollege extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'id_college',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
