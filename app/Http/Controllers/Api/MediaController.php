<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * آپلود فایل رسانه (تصویر، PDF و...)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx',
        ]);

        $file = $request->file('file');
        $directory = 'media/' . date('Y/m');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        return response()->json([
            'message' => 'فایل با موفقیت آپلود شد.',
            'data' => $this->formatFile($path, $file->getClientOriginalName()),
        ]);
    }

    /**
     * لیست فایل‌های آپلود شده
     */
    public function index(Request $request): JsonResponse
    {
        $directory = 'media';
        $perPage = min((int) $request->input('per_page', 24), 100);

        if (!Storage::disk('public')->exists($directory)) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // Recursively list files under media/Y/m/...
        $paths = Storage::disk('public')->allFiles($directory);
        $allFiles = array_map(fn(string $path) => $this->formatFile($path), $paths);

        // Sort by newest first
        usort($allFiles, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        $total = count($allFiles);
        $paged = array_slice($allFiles, 0, $perPage);

        return response()->json([
            'data' => $paged,
            'total' => $total,
        ]);
    }

    /**
     * حذف فایل رسانه
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $url = $request->input('url');
        if (!$url) {
            return response()->json(['message' => 'آدرس فایل الزامی است.'], 422);
        }

        // Extract path from URL
        $path = parse_url($url, PHP_URL_PATH);
        $path = str_replace('/storage/', '', $path);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'فایل با موفقیت حذف شد.']);
    }

    private function formatFile(string $path, ?string $originalName = null): array
    {
        $url = Storage::disk('public')->url($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        $size = Storage::disk('public')->size($path);
        $lastModified = Storage::disk('public')->lastModified($path);

        return [
            'id' => md5($path),
            'name' => $originalName ?: basename($path),
            'url' => $url,
            'path' => $path,
            'size' => $size,
            'type' => $mime,
            'created_at' => date('c', $lastModified),
        ];
    }
}
