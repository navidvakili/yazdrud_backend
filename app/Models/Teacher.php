<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $primaryKey = 'username';
    public $incrementing = false;

    protected $fillable = [
        'username',
        'hokm',
        'bank',
        'hesab',
        'bime',
        'active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
