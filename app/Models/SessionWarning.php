<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionWarning extends Model
{
    protected $fillable = [
        'user_id',
        'new_token_id',
        'poll_token',
        'status',
        'ip_address',
        'user_agent',
        'browser_fingerprint',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the user that owns the session warning (the user being warned).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'username');
    }
}
