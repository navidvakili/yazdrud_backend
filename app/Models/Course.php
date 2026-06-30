<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'section',
        'title',
        'amount',
        'active',
        'image',
        'description',
        'syllabus',
        'duration',
        'instructor',
        'instructor_id',
        'start_date',
        'end_date',
        'registration_start_date',
        'registration_end_date',
        'capacity',
        'registered_count',
    ];

    protected $casts = [
        'active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
        'amount' => 'string',
        'section' => 'string',
    ];

    /**
     * Get the group that this course belongs to.
     */
    public function group()
    {
        return $this->belongsTo(CourseGroup::class, 'group_id');
    }

    /**
     * Get the registrations for this course.
     */
    public function registrations()
    {
        return $this->hasMany(Registertut::class, 'course_id');
    }

    /**
     * Get confirmed (paid) registrations.
     */
    public function confirmedRegistrations()
    {
        return $this->hasMany(Registertut::class, 'course_id')
            ->where('refunded', false)
            ->where(function ($query) {
                $query->where('payment_method', 'online')
                    ->whereHas('payment.transaction', function ($q) {
                        $q->where('status', 'SUCCEED');
                    })
                    ->orWhere('verified_receipt', true);
            });
    }

    /**
     * Get the course instructor (from CourseInstructor model).
     */
    public function courseInstructor()
    {
        return $this->belongsTo(CourseInstructor::class, 'instructor_id');
    }

    /**
     * Get the image URL attribute.
     */
    public function getImageUrlAttribute()
    {
        if ($this->image && file_exists(public_path('storage/' . $this->image))) {
            return asset('storage/' . $this->image);
        }
        return asset('images/default-course.jpg');
    }

    /**
     * Check if the course is available for registration.
     */
    public function isAvailable(): bool
    {
        return $this->active && ($this->capacity == 0 || $this->registered_count < $this->capacity);
    }

    /**
     * Get remaining capacity.
     */
    public function getRemainingCapacityAttribute()
    {
        if ($this->capacity == 0) {
            return 'نامحدود';
        }
        $remaining = $this->capacity - $this->registered_count;
        return $remaining > 0 ? $remaining : 0;
    }

    /**
     * Scope a query to only include active courses.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
