<?php

namespace App\Console\Commands;

use App\Library\MediaMime;
use App\Models\MediaFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SyncMediaFiles extends Command
{
    protected $signature = 'media:sync {--prune : Remove DB rows whose files no longer exist on disk}';

    protected $description = 'Backfill the media_files table from the public storage disk';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $directory = 'media';

        if (!$disk->exists($directory)) {
            $this->info('Media directory not found — nothing to sync.');
            return self::SUCCESS;
        }

        // Guard against overlapping runs
        if (!Cache::add('media.syncing', 1, 300)) {
            $this->error('Another media sync is already running.');
            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $existingPaths = [];

        try {
            foreach ($disk->listContents($directory, true) as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $path = $item->path();
                $existingPaths[$path] = true;

                $mediaFile = MediaFile::updateOrCreate(
                    ['path' => $path],
                    [
                        'name' => basename($path),
                        'mime_type' => MediaMime::fromPath($path),
                        'size' => $item->fileSize() ?? 0,
                        'uploaded_at' => $item->lastModified()
                            ? date('Y-m-d H:i:s', $item->lastModified())
                            : now(),
                    ]
                );

                $mediaFile->wasRecentlyCreated ? $created++ : $updated++;
            }

            if ($this->option('prune')) {
                $pruned = MediaFile::whereNotIn('path', array_keys($existingPaths))->delete();
                $this->info("Pruned {$pruned} rows whose files no longer exist on disk.");
            }
        } finally {
            Cache::forget('media.syncing');
        }

        $this->info("Media sync complete: {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }
}
