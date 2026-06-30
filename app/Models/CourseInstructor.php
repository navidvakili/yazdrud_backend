<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseInstructor extends Model
{
    use HasFactory;

    protected $table = 'course_instructors';

    protected $fillable = [
        'name',
        'specialty',
        'bio',
        'photo',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the photo URL attribute.
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo && file_exists(public_path('storage/' . $this->photo))) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    /**
     * Get the courses for this instructor.
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}
