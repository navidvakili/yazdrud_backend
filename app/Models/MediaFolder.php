<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Virtual media folder — organizational only, never a physical directory.
 */
class MediaFolder extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'color',
        'icon',
        'ordering',
    ];

    protected $casts = [
        'ordering' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')->orderBy('ordering')->orderBy('name');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'folder_id');
    }
}
