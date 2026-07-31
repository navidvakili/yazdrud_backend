<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Library\MediaMime;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /** How long a cached index page stays valid (unless invalidated by an upload/delete) */
    private const INDEX_CACHE_TTL_SECONDS = 1800;

    /**
     * آپلود فایل رسانه (تصویر، PDF و...)
     */
    public function upload(Request $request): JsonResponse
    {
        // Authenticate without triggering Passport's PSR-7 conversion (which breaks with file uploads)
        $user = $this->authenticateViaBearerToken($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        Auth::setUser($user);

        $request->validate([
            'file' => 'required|file|max:102400|mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,mp4,webm,mov,avi,mkv,flv',
        ]);

        $file = $request->file('file');
        $directory = 'media/' . date('Y/m');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;

        if (!$this->storeUploadedFile($file, $path)) {
            if (!Storage::disk('public')->putFileAs($directory, $file, $filename)) {
                return response()->json(['message' => 'خطا در ذخیره‌سازی فایل.'], 500);
            }
        }

        // Persist metadata; the MediaFileObserver invalidates the listing cache
        MediaFile::updateOrCreate(
            ['path' => $path],
            [
                'name' => $file->getClientOriginalName(),
                'mime_type' => MediaMime::fromPath($path),
                'size' => Storage::disk('public')->size($path),
                'uploaded_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'فایل با موفقیت آپلود شد.',
            'data' => $this->formatFile($path, $file->getClientOriginalName()),
        ]);
    }

    /**
     * لیست فایل‌های آپلود شده (با کش صفحه‌بندی‌شده)
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->input('page', 1), 1);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = trim((string) $request->input('search', ''));
        $sortBy = in_array($request->input('sort_by'), ['name', 'size', 'created_at'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Safety net: on a fresh deployment, backfill the metadata table from disk once
        if (MediaFile::query()->doesntExist()) {
            $this->syncMediaFilesFromDisk();
        }

        // Each cache key includes the cache version (bumped by the observer on
        // every upload/delete), so stale pages are never served.
        $cacheKey = sprintf(
            'media.index.%d.%s.%s.%s.%d.%d',
            Cache::get('media.version', 0),
            $sortBy,
            $sortOrder,
            md5($search),
            $page,
            $perPage
        );

        $payload = Cache::remember($cacheKey, now()->addSeconds(self::INDEX_CACHE_TTL_SECONDS), function () use ($search, $sortBy, $sortOrder, $page, $perPage) {
            // 'created_at' maps to the uploaded_at column
            $column = $sortBy === 'created_at' ? 'uploaded_at' : $sortBy;

            $query = MediaFile::query();
            if ($search !== '') {
                $query->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%');
            }
            $query->orderBy($column, $sortOrder)->orderBy('path');

            $files = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => array_map(fn (MediaFile $file) => $this->formatFileFromModel($file), $files->items()),
                'total' => $files->total(),
                'page' => $files->currentPage(),
                'per_page' => $files->perPage(),
                'last_page' => $files->lastPage(),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ];
        });

        return response()->json($payload);
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

        // Remove metadata via a model instance so MediaFileObserver::deleted()
        // fires (query-builder deletes do NOT dispatch model events)
        $file = MediaFile::where('path', $path)->first();
        $file?->delete();

        return response()->json(['message' => 'فایل با موفقیت حذف شد.']);
    }

    /**
     * Build the API response shape for a media file.
     */
    private function formatFileFromModel(MediaFile $file): array
    {
        return [
            'id' => md5($file->path),
            'name' => $file->name,
            'url' => Storage::disk('public')->url($file->path),
            'path' => $file->path,
            'size' => $file->size,
            'type' => $file->mime_type,
            'created_at' => $file->uploaded_at?->format('c') ?? now()->format('c'),
        ];
    }

    /**
     * Backfill the media_files table from the disk (single traversal).
     * Used by the index() safety net; the media:sync command offers the
     * same logic with console output and an optional prune.
     */
    private function syncMediaFilesFromDisk(): void
    {
        if (!Cache::add('media.syncing', 1, 300)) {
            return; // another sync is already running
        }

        try {
            $disk = Storage::disk('public');
            foreach ($disk->listContents('media', true) as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                MediaFile::updateOrCreate(
                    ['path' => $item->path()],
                    [
                        'name' => basename($item->path()),
                        'mime_type' => MediaMime::fromPath($item->path()),
                        'size' => $item->fileSize() ?? 0,
                        'uploaded_at' => $item->lastModified()
                            ? date('Y-m-d H:i:s', $item->lastModified())
                            : now(),
                    ]
                );
            }
        } finally {
            Cache::forget('media.syncing');
        }
    }

    /**
     * احراز هویت از طریق Bearer Token (بدون PSR-7 که با آپلود فایل مشکل دارد)
     */
    private function authenticateViaBearerToken(Request $request): ?User
    {
        // 1. Try standard Passport auth first
        try {
            $user = Auth::guard('api')->user();
            if ($user) {
                return $user;
            }
        } catch (\Exception $e) {
            // PSR-7 conversion error — fall through to manual check
        }

        // 2. Fallback: manually decode JWT to avoid Passport's PSR-7 conversion
        $bearerToken = $request->bearerToken();
        if (!$bearerToken) {
            return null;
        }

        try {
            // Decode JWT payload (second base64 segment)
            $parts = explode('.', $bearerToken);
            if (count($parts) < 2) {
                return null;
            }
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true));
            if (!$payload || empty($payload->jti)) {
                return null;
            }

            $token = \Laravel\Passport\Token::find($payload->jti);
            if (!$token || $token->revoked || $token->expires_at?->isPast()) {
                return null;
            }

            $user = User::find($token->user_id);
            if ($user) {
                // Attach the token only if this Passport version supports it
                // (Token implements ScopeAuthorizable in some versions, plain Model in others)
                if ($token instanceof \Laravel\Passport\Contracts\ScopeAuthorizable) {
                    Auth::setUser($user->withAccessToken($token));
                }
                return $user;
            }
        } catch (\Exception $e) {
            // Silent fallthrough
        }

        return null;
    }

    private function storeUploadedFile($file, string $path): bool
    {
        $disk = Storage::disk('public');
        $directory = dirname($path);

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($this->isCompressibleImage($extension) && $file->getRealPath()) {
            $destination = $disk->path($path);
            return $this->compressUploadedImage($file->getRealPath(), $destination, $extension);
        }

        return (bool) $disk->putFileAs($directory, $file, basename($path));
    }

    private function isCompressibleImage(string $extension): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function compressUploadedImage(string $sourcePath, string $destinationPath, string $extension): bool
    {
        try {
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
                        return false;
                    }
                    $image = imagecreatefromjpeg($sourcePath);
                    if (!$image) {
                        return false;
                    }
                    $result = imagejpeg($image, $destinationPath, 85);
                    imagedestroy($image);
                    return (bool) $result;
                case 'png':
                    if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
                        return false;
                    }
                    $image = imagecreatefrompng($sourcePath);
                    if (!$image) {
                        return false;
                    }
                    imagepalettetotruecolor($image);
                    imagesavealpha($image, true);
                    $result = imagepng($image, $destinationPath, 6);
                    imagedestroy($image);
                    return (bool) $result;
                case 'webp':
                    if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
                        return false;
                    }
                    $image = imagecreatefromwebp($sourcePath);
                    if (!$image) {
                        return false;
                    }
                    $result = imagewebp($image, $destinationPath, 85);
                    imagedestroy($image);
                    return (bool) $result;
                case 'gif':
                    if (!function_exists('imagecreatefromgif') || !function_exists('imagegif')) {
                        return false;
                    }
                    $image = imagecreatefromgif($sourcePath);
                    if (!$image) {
                        return false;
                    }
                    $result = imagegif($image, $destinationPath);
                    imagedestroy($image);
                    return (bool) $result;
                default:
                    return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function formatFile(string $path, ?string $originalName = null): array
    {
        $url = Storage::disk('public')->url($path);
        $size = Storage::disk('public')->size($path);
        $lastModified = Storage::disk('public')->lastModified($path);

        return [
            'id' => md5($path),
            'name' => $originalName ?: basename($path),
            'url' => $url,
            'path' => $path,
            'size' => $size,
            'type' => MediaMime::fromPath($path),
            'created_at' => date('c', $lastModified),
        ];
    }
}
