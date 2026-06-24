<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSurvey extends Model
{
    use HasFactory;

    protected $table = 'course_surveys';

    protected $fillable = [
        'course_id',
        'first_name',
        'last_name',
        'phone_number',
        'rating',
        'suggestions',
        'comment',
        'ip_address',
        'browser_fingerprint',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Get the course that this survey belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
