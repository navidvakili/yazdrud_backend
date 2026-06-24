<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];

    /**
     * Get the courses in this group.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'group_id');
    }
}
