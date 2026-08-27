<?php

namespace App\Models;

use App\Observers\MediaFileObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([MediaFileObserver::class])]
class MediaFile extends Model
{
    protected $fillable = [
        'path',
        'name',
        'title',
        'description',
        'mime_type',
        'size',
        'folder_id',
        'uploaded_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'folder_id' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * همه پوشه‌های مجازی که این فایل در آن‌ها ثبت شده است (چند گروهی).
     */
    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(MediaFolder::class, 'media_file_folder')
            ->withTimestamps()
            ->orderBy('media_folder_id');
    }
}
