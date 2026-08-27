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
            'file' => 'required|file|max:102400|mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,zip,rar,7z,tar,gz,mp4,webm,mov,avi,mkv,flv,mp3,wav,ogg,oga,flac,aac,m4a,opus',
        ]);

        $folderId = $request->input('folder_id') ? (int) $request->input('folder_id') : null;
        if ($folderId !== null && !\App\Models\MediaFolder::where('id', $folderId)->exists()) {
            return response()->json(['message' => 'پوشه انتخاب‌شده معتبر نیست.'], 422);
        }
        $folderIds = $this->parseFolderIds($request);
        if ($folderIds === []) {
            $folderIds = $folderId !== null ? [$folderId] : [];
        } elseif ($folderId === null) {
            $folderId = $folderIds[0] ?? null;
        }

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
        $model = MediaFile::updateOrCreate(
            ['path' => $path],
            [
                'name' => $file->getClientOriginalName(),
                'mime_type' => MediaMime::fromPath($path),
                'size' => Storage::disk('public')->size($path),
                'folder_id' => $folderId,
                'uploaded_at' => now(),
            ]
        );

        // Register the file in every selected virtual folder (multi-group)
        if ($folderIds !== []) {
            $model->folders()->sync($folderIds);
        }

        return response()->json([
            'message' => 'فایل با موفقیت آپلود شد.',
            'data' => $this->formatFileFromModel($model->fresh()),
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
        $type = strtolower((string) $request->input('type', 'all'));
        if (!in_array($type, ['image', 'video', 'audio', 'document', 'all'], true)) {
            $type = 'all';
        }
        $folderId = $request->input('folder_id') !== null && $request->input('folder_id') !== ''
            ? (int) $request->input('folder_id')
            : null;
        $folderIds = $this->parseFolderIds($request);
        if ($folderId !== null) {
            $folderIds[] = $folderId;
        }
        $folderIds = array_values(array_unique(array_filter($folderIds)));
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
            'media.index.%d.%s.%s.%s.%d.%d.%s.%s',
            Cache::get('media.version', 0),
            $sortBy,
            $sortOrder,
            md5($search),
            $page,
            $perPage,
            $folderIds === [] ? 'all' : implode(',', $folderIds),
            $type
        );

        $payload = Cache::remember($cacheKey, now()->addSeconds(self::INDEX_CACHE_TTL_SECONDS), function () use ($search, $sortBy, $sortOrder, $page, $perPage, $folderIds, $type) {
            // 'created_at' maps to the uploaded_at column
            $column = $sortBy === 'created_at' ? 'uploaded_at' : $sortBy;

            $query = MediaFile::query()->with('folders');
            if ($search !== '') {
                $query->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%');
            }
            if ($folderIds !== []) {
                $query->whereHas('folders', function ($q) use ($folderIds) {
                    $q->whereIn('media_file_folder.media_folder_id', $folderIds);
                });
            }
            $this->applyTypeFilter($query, $type);
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
                'type' => $type,
            ];
        });

        return response()->json($payload);
    }

    /**
     * لیست عمومی فایل‌های رسانه — برای ویجت‌های عمومی (مخزن اسناد / گالری).
     * فایل‌ها در storage عمومی هستند و از قبل بدون احراز هویت قابل دسترسی‌اند؛
     * این متد فقط فهرست آن‌ها را بدون auth در اختیار سایت عمومی می‌گذارد.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $type = strtolower((string) $request->input('type', 'all'));
        if (!in_array($type, ['image', 'video', 'audio', 'document', 'all'], true)) {
            $type = 'all';
        }
        $folderId = $request->input('folder_id') !== null && $request->input('folder_id') !== ''
            ? (int) $request->input('folder_id')
            : null;
        $folderIds = $this->parseFolderIds($request);
        if ($folderId !== null) {
            $folderIds[] = $folderId;
        }
        $folderIds = array_values(array_unique(array_filter($folderIds)));

        // Safety net: on a fresh deployment, backfill the metadata table from disk once
        if (MediaFile::query()->doesntExist()) {
            $this->syncMediaFilesFromDisk();
        }

        $cacheKey = sprintf(
            'media.public.%d.%d.%s.%s',
            Cache::get('media.version', 0),
            $perPage,
            $folderIds === [] ? 'all' : implode(',', $folderIds),
            $type
        );

        $payload = Cache::remember($cacheKey, now()->addSeconds(self::INDEX_CACHE_TTL_SECONDS), function () use ($perPage, $folderIds, $type) {
            $query = MediaFile::query()->with('folders');
            if ($folderIds !== []) {
                $query->whereHas('folders', function ($q) use ($folderIds) {
                    $q->whereIn('media_file_folder.media_folder_id', $folderIds);
                });
            }
            $this->applyTypeFilter($query, $type);
            $query->orderByDesc('uploaded_at')->orderBy('path');

            $files = $query->paginate($perPage, ['*'], 'page', 1);

            return [
                'data' => array_map(fn (MediaFile $file) => $this->formatFileFromModel($file), $files->items()),
                'total' => $files->total(),
                'per_page' => $files->perPage(),
                'last_page' => $files->lastPage(),
                'type' => $type,
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
     * Move a media file into a virtual folder (or unassign with folder_id=null).
     * The file is resolved via `path` (or `url`) in the request body, falling
     * back to the md5-of-path `id` in the URL.
     */
    public function move(Request $request, string $id): JsonResponse
    {
        $folderId = $request->input('folder_id') !== null && $request->input('folder_id') !== ''
            ? (int) $request->input('folder_id')
            : null;
        $folderIds = $this->parseFolderIds($request);
        if ($folderIds !== []) {
            // Explicit multi-folder list wins over the legacy single folder_id
            if ($folderId === null) {
                $folderId = $folderIds[0];
            }
        } elseif ($folderId !== null) {
            $folderIds = [$folderId];
        }

        foreach ($folderIds as $fid) {
            if (!\App\Models\MediaFolder::where('id', $fid)->exists()) {
                return response()->json(['message' => 'پوشه انتخاب‌شده معتبر نیست.'], 422);
            }
        }

        $path = (string) $request->input('path', '');
        if ($path === '' && $request->input('url') !== null) {
            $path = ltrim(parse_url((string) $request->input('url'), PHP_URL_PATH) ?: '', '/');
            $path = preg_replace('#^storage/#', '', $path) ?: '';
        }

        $file = null;
        if ($path !== '') {
            $file = MediaFile::where('path', $path)->first();
        }
        if (!$file) {
            // Fallback: id is md5(storage path)
            $file = MediaFile::get()->first(fn (MediaFile $item) => md5($item->path) === $id);
        }

        if (!$file) {
            return response()->json(['message' => 'فایل موردنظر یافت نشد.'], 404);
        }

        // Sync the many-to-many membership; the legacy column mirrors the first folder
        $file->folders()->sync($folderIds);
        $file->folder_id = $folderIds[0] ?? null;
        $file->save();

        Cache::increment('media.version');

        return response()->json([
            'message' => 'فایل با موفقیت منتقل شد.',
            'data' => $this->formatFileFromModel($file->fresh()),
        ]);
    }

    /**
     * Stream a media file with CORS headers (for EmbedPDF / video.js fetches).
     * The file is resolved via `id` = md5(storage path), same as `move()`.
     * Public route — files in public storage are already reachable without auth.
     */
    public function stream(Request $request, string $id): \Symfony\Component\HttpFoundation\StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $file = MediaFile::get()->first(fn (MediaFile $item) => md5($item->path) === $id);

        if (!$file) {
            abort(404, 'فایل موردنظر یافت نشد.');
        }

        $disk = Storage::disk('public');
        $path = $file->path;

        if (!$disk->exists($path)) {
            abort(404, 'فایل روی دیسک وجود ندارد.');
        }

        $mime = $file->mime_type ?: 'application/octet-stream';
        $size = $disk->size($path);

        // Range support (required for video seeking / trimming in browsers)
        $rangeHeader = $request->header('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                // suffix range: bytes=-N  →  the LAST N bytes (moov at file end)
                $start = max(0, $size - (int) $m[2]);
                $end = $size - 1;
            } else {
                $start = $m[1] !== '' ? (int) $m[1] : 0;
                $end = $m[2] !== '' ? (int) $m[2] : $size - 1;
            }

            if ($start > $end || $start >= $size) {
                return response('', 416)->header('Content-Range', "bytes */{$size}");
            }

            $end = min($end, $size - 1);
            $length = $end - $start + 1;

            return response()->stream(function () use ($disk, $path, $start, $length) {
                $stream = $disk->readStream($path);
                if (!$stream) {
                    return;
                }
                fseek($stream, $start);
                $remaining = $length;
                while ($remaining > 0 && !feof($stream)) {
                    $chunk = fread($stream, min(8192 * 16, $remaining));
                    if ($chunk === false) {
                        break;
                    }
                    $remaining -= strlen($chunk);
                    echo $chunk;
                    flush();
                }
                fclose($stream);
            }, 206, [
                'Content-Type' => $mime,
                'Content-Length' => $length,
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Update a media file's metadata (title / description).
     * The file is resolved via `id` = md5(storage path), same as `move()`.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $file = MediaFile::get()->first(fn (MediaFile $item) => md5($item->path) === $id);

        if (!$file) {
            return response()->json(['message' => 'فایل موردنظر یافت نشد.'], 404);
        }

        $data = $request->only(['title', 'description']);
        $data['title'] = isset($data['title']) ? trim((string) $data['title']) : null;
        $data['description'] = isset($data['description']) ? trim((string) $data['description']) : null;

        $file->title = $data['title'] !== '' ? $data['title'] : null;
        $file->description = $data['description'] !== '' ? $data['description'] : null;
        $file->save();

        Cache::increment('media.version');

        return response()->json([
            'message' => 'اطلاعات فایل با موفقیت به‌روزرسانی شد.',
            'data' => $this->formatFileFromModel($file->fresh()),
        ]);
    }

    /**
     * Build the API response shape for a media file.
     */
    private function formatFileFromModel(MediaFile $file): array
    {
        // Prefer the eager-loaded relation; fall back to a lazy load so single
        // file responses (upload / move / update) stay correct too.
        $folderIds = $file->relationLoaded('folders')
            ? $file->folders->pluck('id')
            : $file->folders()->pluck('media_folder_id');

        return [
            'id' => md5($file->path),
            'name' => $file->name,
            'title' => $file->title,
            'description' => $file->description,
            'url' => Storage::disk('public')->url($file->path),
            'path' => $file->path,
            'size' => $file->size,
            'type' => $file->mime_type,
            'folder_id' => $file->folder_id,
            'folder_ids' => $folderIds->map(fn ($id) => (int) $id)->values()->all(),
            'created_at' => $file->uploaded_at?->format('c') ?? now()->format('c'),
        ];
    }

    /**
     * Parse the `folder_ids` request input — accepts a JSON array, a repeated
     * form-style array (folder_ids[]=1&folder_ids[]=2), a JSON-encoded string
     * ("[1,2,3]"), or a comma-separated string ("1,2,3"). Returns a list of
     * integer ids (may be empty).
     */
    private function parseFolderIds(Request $request): array
    {
        $raw = $request->input('folder_ids');
        if (is_array($raw)) {
            $ids = array_map('intval', $raw);
        } elseif (is_string($raw) && trim($raw) !== '') {
            $trimmed = trim($raw);
            // Multipart fields arrive as strings — detect a JSON array first
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $ids = array_map('intval', $decoded);
            } else {
                $ids = array_map('intval', explode(',', $trimmed));
            }
        } else {
            return [];
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    /**
     * Apply a media type filter to the query.
     * 'document' = everything that is not an image, video or audio file.
     */
    private function applyTypeFilter($query, string $type): void
    {
        if ($type === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($type === 'video') {
            $query->where('mime_type', 'like', 'video/%');
        } elseif ($type === 'audio') {
            $query->where('mime_type', 'like', 'audio/%');
        } elseif ($type === 'document') {
            $query->where(function ($q) {
                $q->whereNot('mime_type', 'like', 'image/%')
                    ->whereNot('mime_type', 'like', 'video/%')
                    ->whereNot('mime_type', 'like', 'audio/%');
            });
        }
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
