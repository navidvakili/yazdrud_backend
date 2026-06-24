<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPinnedMenu extends Model
{
    protected $table = 'user_pinned_menus';

    protected $fillable = [
        'username',
        'menu_id',
    ];

    /**
     * Get the user that owns the pinned menu.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
