<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsCommentController extends Controller
{
    /**
     * Get approved comments for a news article (public)
     */
    public function index(int $newsId): JsonResponse
    {
        $news = News::find($newsId);
        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        $comments = NewsComment::where('news_id', $newsId)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'author_name', 'content', 'created_at']);

        return response()->json([
            'data' => $comments->map(fn($c) => [
                'id' => $c->id,
                'author_name' => $c->author_name,
                'content' => $c->content,
                'created_at' => $c->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Submit a new comment (public)
     */
    public function store(Request $request, int $newsId): JsonResponse
    {
        $news = News::find($newsId);
        if (!$news) {
            return response()->json(['message' => 'خبر یافت نشد'], 404);
        }

        if (!$news->comments_enabled) {
            return response()->json(['message' => 'ثبت نظر برای این خبر غیرفعال است'], 403);
        }

        $validator = Validator::make($request->all(), [
            'author_name' => 'required|string|max:200',
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطا در اعتبارسنجی',
                'errors' => $validator->errors(),
            ], 422);
        }

        $comment = NewsComment::create([
            'news_id' => $newsId,
            'author_name' => $request->input('author_name'),
            'content' => $request->input('content'),
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'نظر شما با موفقیت ثبت شد و پس از تایید مدیر نمایش داده خواهد شد.',
            'data' => [
                'id' => $comment->id,
                'author_name' => $comment->author_name,
                'content' => $comment->content,
                'created_at' => $comment->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * List all comments (admin/support) — with moderation filters
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = NewsComment::with(['news:id,title', 'approver:id,fname,lname']);

        // Filter by news_id
        if ($request->filled('news_id')) {
            $query->where('news_id', $request->input('news_id'));
        }

        // Filter by approval status
        if ($request->filled('status')) {
            if ($request->input('status') === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->input('status') === 'approved') {
                $query->where('is_approved', true);
            }
        }

        $perPage = min((int) $request->input('per_page', 20), 50);
        $comments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $comments->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'news_id' => $c->news_id,
                'news_title' => $c->news?->title,
                'author_name' => $c->author_name,
                'content' => $c->content,
                'is_approved' => $c->is_approved,
                'approved_at' => $c->approved_at?->toISOString(),
                'approved_by_name' => $c->approver ? trim(($c->approver->fname ?? '') . ' ' . ($c->approver->lname ?? '')) : null,
                'created_at' => $c->created_at->toISOString(),
                'updated_at' => $c->updated_at->toISOString(),
            ];
        });

        return response()->json($comments);
    }

    /**
     * Approve a comment (admin/support)
     */
    public function approve(int $id): JsonResponse
    {
        $comment = NewsComment::find($id);
        if (!$comment) {
            return response()->json(['message' => 'نظر یافت نشد'], 404);
        }

        $comment->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => request()->user()->id,
        ]);

        return response()->json([
            'message' => 'نظر با موفقیت تایید شد.',
            'data' => [
                'id' => $comment->id,
                'is_approved' => true,
                'approved_at' => $comment->fresh()->approved_at->toISOString(),
            ],
        ]);
    }

    /**
     * Delete a comment (admin/support)
     */
    public function destroy(int $id): JsonResponse
    {
        $comment = NewsComment::find($id);
        if (!$comment) {
            return response()->json(['message' => 'نظر یافت نشد'], 404);
        }

        $comment->delete();

        return response()->json(['message' => 'نظر با موفقیت حذف شد.']);
    }
}
