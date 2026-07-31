<?php

namespace App\Models;

use App\Observers\MediaFileObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MediaFileObserver::class])]
class MediaFile extends Model
{
    protected $fillable = [
        'path',
        'name',
        'mime_type',
        'size',
        'uploaded_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_at' => 'datetime',
    ];
}
