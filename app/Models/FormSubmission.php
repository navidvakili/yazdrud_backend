<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'tracking_code',
        'respondent_name',
        'respondent_email',
        'respondent_role',
        'status',
        'answers',
        'score_total',
        'grade_label',
        'ip_address',
        'user_agent',
        'completion_time_seconds',
        'internal_notes',
        'expert_assigned',
        'submitted_at',
    ];

    protected $casts = [
        'answers'      => 'array',
        'submitted_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
