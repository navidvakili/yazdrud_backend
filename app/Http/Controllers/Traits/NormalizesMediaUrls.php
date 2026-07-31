<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait NormalizesMediaUrls
{
    private function normalizeMediaUrlValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $storagePath = $this->getStoragePathFromUrl($value);
            if ($storagePath !== null) {
                return $storagePath;
            }

            return $value;
        }

        if (Str::startsWith($value, '/storage/')) {
            return ltrim(Str::after($value, '/storage/'), '/');
        }

        if (Str::startsWith($value, 'storage/')) {
            return ltrim(Str::after($value, 'storage/'), '/');
        }

        if (Str::startsWith($value, '/media/')) {
            return ltrim($value, '/');
        }

        return $value;
    }

    private function resolveMediaUrlValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $storagePath = $this->getStoragePathFromUrl($value);
            if ($storagePath !== null) {
                return Storage::disk('public')->url($storagePath);
            }

            return $value;
        }

        if (Str::startsWith($value, '/storage/')) {
            $value = ltrim(Str::after($value, '/storage/'), '/');
        }

        if (Str::startsWith($value, 'storage/')) {
            $value = ltrim(Str::after($value, 'storage/'), '/');
        }

        if (Str::startsWith($value, '/media/')) {
            $value = ltrim($value, '/');
        }

        return Storage::disk('public')->url($value);
    }

    private function getStoragePathFromUrl(string $url): ?string
    {
        $parsed = @parse_url($url);
        if (!is_array($parsed) || empty($parsed['path'])) {
            return null;
        }

        $path = $parsed['path'];

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::after($path, '/storage/'), '/');
        }

        if (Str::startsWith($path, '/media/')) {
            return ltrim($path, '/');
        }

        return null;
    }

    private function normalizePhotoReportImages(array $images): array
    {
        return array_map(static function ($item) {
            return [
                'url' => $this->normalizeMediaUrlValue($item['url'] ?? null),
                'title' => $item['title'] ?? null,
            ];
        }, $images);
    }

    private function resolvePhotoReportImages(array $images): array
    {
        return array_map(static function ($item) {
            return [
                'url' => $this->resolveMediaUrlValue($item['url'] ?? null),
                'title' => $item['title'] ?? null,
            ];
        }, $images);
    }

    private function normalizeAttachments(array $attachments): array
    {
        return array_map(static function ($item) {
            return array_merge($item, [
                'url' => $this->normalizeMediaUrlValue($item['url'] ?? null),
            ]);
        }, $attachments);
    }

    private function resolveAttachments(array $attachments): array
    {
        return array_map(static function ($item) {
            return array_merge($item, [
                'url' => $this->resolveMediaUrlValue($item['url'] ?? null),
            ]);
        }, $attachments);
    }

    private function normalizeSliderProjectData(array $projectData): array
    {
        if (isset($projectData['background']['imageUrl'])) {
            $projectData['background']['imageUrl'] = $this->normalizeMediaUrlValue($projectData['background']['imageUrl']);
        }

        if (isset($projectData['background']['videoUrl'])) {
            $projectData['background']['videoUrl'] = $this->normalizeMediaUrlValue($projectData['background']['videoUrl']);
        }

        if (!empty($projectData['slides']) && is_array($projectData['slides'])) {
            $projectData['slides'] = array_map([$this, 'normalizeSliderProjectSlide'], $projectData['slides']);
        }

        return $projectData;
    }

    private function normalizeSliderProjectSlide(array $slide): array
    {
        if (isset($slide['background']['imageUrl'])) {
            $slide['background']['imageUrl'] = $this->normalizeMediaUrlValue($slide['background']['imageUrl']);
        }

        if (isset($slide['background']['videoUrl'])) {
            $slide['background']['videoUrl'] = $this->normalizeMediaUrlValue($slide['background']['videoUrl']);
        }

        if (!empty($slide['layers']) && is_array($slide['layers'])) {
            $slide['layers'] = array_map([$this, 'normalizeSliderProjectLayer'], $slide['layers']);
        }

        return $slide;
    }

    private function normalizeSliderProjectLayer(array $layer): array
    {
        if (isset($layer['content']) && in_array($layer['type'] ?? null, ['image', 'video'], true)) {
            $layer['content'] = $this->normalizeMediaUrlValue($layer['content'] ?? null);
        }

        return $layer;
    }

    private function resolveSliderProjectData(array $projectData): array
    {
        if (isset($projectData['background']['imageUrl'])) {
            $projectData['background']['imageUrl'] = $this->resolveMediaUrlValue($projectData['background']['imageUrl']);
        }

        if (isset($projectData['background']['videoUrl'])) {
            $projectData['background']['videoUrl'] = $this->resolveMediaUrlValue($projectData['background']['videoUrl']);
        }

        if (!empty($projectData['slides']) && is_array($projectData['slides'])) {
            $projectData['slides'] = array_map([$this, 'resolveSliderProjectSlide'], $projectData['slides']);
        }

        return $projectData;
    }

    private function resolveSliderProjectSlide(array $slide): array
    {
        if (isset($slide['background']['imageUrl'])) {
            $slide['background']['imageUrl'] = $this->resolveMediaUrlValue($slide['background']['imageUrl']);
        }

        if (isset($slide['background']['videoUrl'])) {
            $slide['background']['videoUrl'] = $this->resolveMediaUrlValue($slide['background']['videoUrl']);
        }

        if (!empty($slide['layers']) && is_array($slide['layers'])) {
            $slide['layers'] = array_map([$this, 'resolveSliderProjectLayer'], $slide['layers']);
        }

        return $slide;
    }

    private function resolveSliderProjectLayer(array $layer): array
    {
        if (isset($layer['content']) && in_array($layer['type'] ?? null, ['image', 'video'], true)) {
            $layer['content'] = $this->resolveMediaUrlValue($layer['content'] ?? null);
        }

        return $layer;
    }
}
