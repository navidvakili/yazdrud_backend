<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermCoupon extends Model
{
    use HasFactory;

    protected $table = 'term_coupons';

    protected $fillable = [
        'title',
        'type',
        'type_discount',
        'term_id',
        'course_id',
        'group_id',
        'code',
        'start_date',
        'finish_date',
        'capacity',
        'used_count',
        'is_active',
        'value',
        'max_discount',
        'national_code',
    ];

    /**
     * Get the course group that this coupon is restricted to (if any).
     */
    public function group()
    {
        return $this->belongsTo(CourseGroup::class, 'group_id');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'used_count' => 'integer',
        'value' => 'integer',
        'max_discount' => 'integer',
    ];

    /**
     * Get the course that this coupon is restricted to (if any).
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get the remaining uses.
     */
    public function getRemainingAttribute(): int
    {
        return max(0, $this->capacity - $this->used_count);
    }

    /**
     * Get formatted value.
     */
    public function getValueFormattedAttribute(): string
    {
        if ($this->type_discount === 'percent') {
            return "{$this->value}%";
        }
        return number_format($this->value) . ' ریال';
    }

    /**
     * Scope active coupons.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if coupon is valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->used_count >= $this->capacity) return false;
        return true;
    }
}
