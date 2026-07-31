<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
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
        $page = max((int) $request->input('page', 1), 1);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = trim((string) $request->input('search', ''));
        $sortBy = in_array($request->input('sort_by'), ['name', 'size', 'created_at'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!Storage::disk('public')->exists($directory)) {
            return response()->json(['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 0]);
        }

        // Recursively list files under media/Y/m/...
        $paths = Storage::disk('public')->allFiles($directory);
        $allFiles = array_map(fn(string $path) => $this->formatFile($path), $paths);

        if ($search !== '') {
            $allFiles = array_filter($allFiles, fn(array $file) => mb_stripos($file['name'], $search) !== false);
        }

        usort($allFiles, function (array $a, array $b) use ($sortBy, $sortOrder) {
            if ($sortBy === 'name') {
                $result = strcasecmp($a['name'], $b['name']);
            } elseif ($sortBy === 'size') {
                $result = $a['size'] <=> $b['size'];
            } else {
                $result = strtotime($a['created_at']) <=> strtotime($b['created_at']);
            }

            if ($result === 0) {
                $result = strcmp($a['path'], $b['path']);
            }

            return $sortOrder === 'asc' ? $result : -$result;
        });

        $total = count($allFiles);
        $lastPage = (int) max(1, ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($allFiles, $offset, $perPage);

        return response()->json([
            'data' => $paged,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
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
                Auth::setUser($user->withAccessToken($token));
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
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        // Fallback for video mime types that storage may misdetect
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $videoMimes = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska', 'flv' => 'video/x-flv'];
        if (isset($videoMimes[$ext])) {
            $mime = $videoMimes[$ext];
        }
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
