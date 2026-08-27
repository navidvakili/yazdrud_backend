<?php

namespace App\Observers;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Cache;

class MediaFileObserver
{
    /**
     * Invalidate the media listing cache whenever the file list changes.
     *
     * The project's cache driver is `database`, which does not support
     * cache tags, so we bump a monotonic version key instead. Every
     * index() cache key includes this version, making stale entries
     * unreachable the moment a file is uploaded or deleted.
     */
    private function bumpCacheVersion(): void
    {
        // Milliseconds, not seconds — upload+delete within the same second
        // must still produce a different version key
        Cache::put('media.version', (int) round(microtime(true) * 1000));
    }

    public function created(MediaFile $mediaFile): void
    {
        $this->bumpCacheVersion();
    }

    public function updated(MediaFile $mediaFile): void
    {
        // Title/description/folder edits change what the (public) listing serves
        $this->bumpCacheVersion();
    }

    public function deleted(MediaFile $mediaFile): void
    {
        $this->bumpCacheVersion();
    }
}
