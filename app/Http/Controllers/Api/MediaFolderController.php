<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Virtual media folders CRUD.
 *
 * Folders are purely logical (DB rows) — no physical directories are
 * created on disk. They organize the media library in the gallery app
 * and are NOT shown inside the MediaManager dialog.
 */
class MediaFolderController extends Controller
{
    /**
     * List all folders (flat — the frontend builds the tree via parent_id).
     */
    public function index(): JsonResponse
    {
        $folders = MediaFolder::query()
            ->orderBy('ordering')
            ->orderBy('name')
            ->get()
            ->map(fn (MediaFolder $folder) => $this->formatFolder($folder));

        return response()->json(['data' => $folders]);
    }

    /**
     * Create a virtual folder.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:120',
            'parent_id' => 'nullable|integer|exists:media_folders,id',
            'color'     => 'nullable|string|max:20',
            'icon'      => 'nullable|string|max:40',
        ]);

        $folder = MediaFolder::create([
            'name'      => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'color'     => $validated['color'] ?? '#0d9488',
            'icon'      => $validated['icon'] ?? 'Folder',
            'ordering'  => (int) MediaFolder::query()->max('ordering') + 1,
        ]);

        return response()->json(['data' => $this->formatFolder($folder), 'message' => 'پوشه با موفقیت ایجاد شد.'], 201);
    }

    /**
     * Update a virtual folder.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $folder = MediaFolder::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:120',
            'parent_id' => 'nullable|integer|exists:media_folders,id',
            'color'     => 'nullable|string|max:20',
            'icon'      => 'nullable|string|max:40',
            'ordering'  => 'nullable|integer|min:0',
        ]);

        // Prevent circular parent assignment
        if (!empty($validated['parent_id']) && $validated['parent_id'] != $folder->id) {
            $ancestorIds = $this->collectAncestorIds($validated['parent_id']);
            if (in_array($folder->id, $ancestorIds, true)) {
                return response()->json(['message' => 'نمی‌توان یک پوشه را زیرمجموعه‌ی خودش یا زیرمجموعه‌هایش قرار داد.'], 422);
            }
        }

        $folder->update(array_merge($validated, ['parent_id' => $validated['parent_id'] ?? null]));

        return response()->json(['data' => $this->formatFolder($folder->fresh()), 'message' => 'پوشه با موفقیت به‌روزرسانی شد.']);
    }

    /**
     * Delete a virtual folder. Files inside it are kept and unassigned.
     */
    public function destroy(int $id): JsonResponse
    {
        $folder = MediaFolder::findOrFail($id);

        // Unassign direct children files (physical files are untouched)
        MediaFile::where('folder_id', $folder->id)->update(['folder_id' => null]);

        // Reparent child folders to the deleted folder's parent
        MediaFolder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);

        $folder->delete();

        return response()->json(['message' => 'پوشه با موفقیت حذف شد.']);
    }

    private function formatFolder(MediaFolder $folder): array
    {
        return [
            'id'        => $folder->id,
            'name'      => $folder->name,
            'parent_id' => $folder->parent_id,
            'color'     => $folder->color,
            'icon'      => $folder->icon,
            'ordering'  => $folder->ordering,
        ];
    }

    private function collectAncestorIds(int $parentId): array
    {
        $ids = [];
        $current = $parentId;
        $guard = 0;
        while ($current && $guard++ < 100) {
            $ids[] = $current;
            $current = (int) (MediaFolder::where('id', $current)->value('parent_id'));
        }
        return $ids;
    }
}
