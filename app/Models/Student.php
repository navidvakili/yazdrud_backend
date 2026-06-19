<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_edu';
    public $incrementing = false;

    protected $fillable = [
        'id_edu',
        'jensiat',
        'field_id',
        'birthday',
        'father',
        'marital',
        'family_head',
        'vaccine',
        'address',
        'nationality',
        'id_number',
        'birth_year',
        'birth_place',
        'religion',
        'home_phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_edu', 'username');
    }

    public function field()
    {
        return $this->belongsTo(Field::class, 'field_id', 'id');
    }

    public function thesis()
    {
        return $this->hasOne(Thesis::class, 'id_edu', 'id_edu')->withDefault();
    }

    public function getIdCollegeAttribute()
    {
        $field = Field::where('id', $this->field_id)->first();
        return $field->college_id ?? '';
    }

    public function getMaghteAttribute()
    {
        $field = Field::where('id', $this->field_id)->first();
        return $field->maghte ?? '';
    }
}
