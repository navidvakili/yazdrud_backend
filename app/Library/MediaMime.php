<?php

namespace App\Library;

use Symfony\Component\Mime\MimeTypes;

/**
 * MediaMime — MIME type detection from file extension
 *
 * Detecting MIME from the extension (instead of reading file contents)
 * makes media listing fast, and centralizing it here keeps the upload
 * path and the media:sync backfill command consistent.
 */
class MediaMime
{
    /** Common aliases browsers/frontend expect for video files */
    private const VIDEO_MIMES = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'flv' => 'video/x-flv',
    ];

    public static function fromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '') {
            return 'application/octet-stream';
        }

        if (isset(self::VIDEO_MIMES[$extension])) {
            return self::VIDEO_MIMES[$extension];
        }

        $mimes = MimeTypes::getDefault()->getMimeTypes($extension);

        return $mimes[0] ?? 'application/octet-stream';
    }
}
