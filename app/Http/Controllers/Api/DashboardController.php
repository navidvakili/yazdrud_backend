<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPinnedMenu;
use App\Models\Registertut;
use App\Models\RegistrationInstallment;
use App\Models\CourseSurvey;
use App\Models\Course;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    /**
     * Get the list of pinned menu IDs for the authenticated user.
     */
    public function pinnedMenus(Request $request): JsonResponse
    {
        $pinned = UserPinnedMenu::where('username', $request->user()->username)
            ->pluck('menu_id');

        return response()->json([
            'data' => $pinned,
        ]);
    }

    /**
     * Pin a menu item for the authenticated user.
     */
    public function pin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'menu_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $username = $request->user()->username;
        $menuId = $request->menu_id;

        // Check if already pinned
        $exists = UserPinnedMenu::where('username', $username)
            ->where('menu_id', $menuId)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'این آیتم قبلاً پین شده است',
            ], 409);
        }

        UserPinnedMenu::create([
            'username' => $username,
            'menu_id' => $menuId,
        ]);

        return response()->json([
            'message' => 'آیتم با موفقیت پین شد',
        ], 201);
    }

    /**
     * Unpin a menu item for the authenticated user.
     */
    public function unpin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'menu_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $username = $request->user()->username;
        $menuId = $request->menu_id;

        $deleted = UserPinnedMenu::where('username', $username)
            ->where('menu_id', $menuId)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => 'این آیتم پین نشده است',
            ], 404);
        }

        return response()->json([
            'message' => 'آیتم با موفقیت از پین خارج شد',
        ]);
    }

    /**
     * Get overview data for the management dashboard.
     * Returns widgets: latest registrations, current week installments,
     * recent surveys, unapproved receipts, pending certificates, quick stats.
     */
    public function overview(Request $request): JsonResponse
    {
        // Current Jalali (Shamsi) year boundaries in Gregorian
        $now = Verta::now();
        $yearStart = (clone $now)->startYear()->toCarbon();
        $yearEnd = (clone $now)->endYear()->toCarbon();

        // 1. Latest registrations (last 10, current year)
        $latestRegistrations = Registertut::with('course')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($reg) => [
                'id'               => $reg->id,
                'fullname'         => $reg->fullname,
                'course_title'     => $reg->course?->title,
                'mobile'           => $reg->mobile,
                'payment_method'   => $reg->payment_method,
                'status'           => $reg->actual_status,
                'status_text'      => $reg->actual_status_text,
                'created_at'       => $this->toJalali($reg->created_at, 'Y/m/d H:i'),
            ]);

        // 2. Current week (Shamsi) pending installments
        $startOfWeek = (clone $now)->startWeek()->format('Y/m/d');
        $endOfWeek = (clone $now)->endWeek()->format('Y/m/d');

        $currentWeekInstallments = RegistrationInstallment::with([
            'registration.course',
        ])
            ->where('status', 'pending')
            // Normalize dashes to slashes so the comparison works regardless of storage format
            ->whereRaw("REPLACE(due_date, '-', '/') BETWEEN ? AND ?", [$startOfWeek, $endOfWeek])
            ->get()
            ->map(fn($inst) => [
                'id'               => $inst->id,
                'title'            => $inst->title,
                'amount'           => $inst->amount,
                'amount_formatted' => number_format($inst->amount ?? 0),
                'due_date'         => $inst->due_date,
                'fullname'         => $inst->registration?->fullname,
                'course_title'     => $inst->registration?->course?->title,
                'mobile'           => $inst->registration?->mobile,
            ]);

        // 3. Recent surveys (last 10, current year)
        $recentSurveys = CourseSurvey::with('course')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($survey) => [
                'id'           => $survey->id,
                'fullname'     => $survey->full_name,
                'course_title' => $survey->course?->title,
                'rating'       => $survey->rating,
                'phone_number' => $survey->phone_number,
                'comment'      => $survey->comment,
                'created_at'   => $this->toJalali($survey->created_at, 'Y/m/d H:i'),
            ]);

        // 4. Unapproved bank receipts (pending verification, current year)
        $unapprovedReceiptsQuery = Registertut::with('course')
            ->where('payment_method', 'bank')
            ->where('verified_receipt', false)
            ->where(function ($q) {
                $q->where('rejected_receipt', false)
                  ->orWhereNull('rejected_receipt');
            })
            ->whereBetween('created_at', [$yearStart, $yearEnd]);

        $unapprovedReceiptsCount = (clone $unapprovedReceiptsQuery)->count();
        $unapprovedReceiptsList = (clone $unapprovedReceiptsQuery)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($reg) => [
                'id'               => $reg->id,
                'fullname'         => $reg->fullname,
                'course_title'     => $reg->course?->title,
                'mobile'           => $reg->mobile,
                'amount'           => intval($reg->course?->amount ?? 0),
                'amount_formatted' => number_format(intval($reg->course?->amount ?? 0)),
                'created_at'       => $this->toJalali($reg->created_at, 'Y/m/d H:i'),
            ]);

        // 5. Pending certificates (paid registrations awaiting certificate approval, current year)
        $pendingCertificatesCount = Registertut::where('certificate_approved', false)
            ->where('refunded', false)
            ->where(function ($q) {
                $q->where('payment_method', 'online')
                  ->whereHas('payment.transaction', fn($t) => $t->where('status', 'SUCCEED'))
                  ->orWhere('verified_receipt', true);
            })
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->count();

        // 6. Quick stats for the header
        $activeCoursesCount = Course::where('active', true)->count();

        $totalConfirmedRegistrations = Registertut::where('refunded', false)
            ->where(function ($q) {
                $q->where('payment_method', 'online')
                  ->whereHas('payment.transaction', fn($t) => $t->where('status', 'SUCCEED'))
                  ->orWhere('verified_receipt', true);
            })
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->count();

        return response()->json([
            'latest_registrations'        => $latestRegistrations,
            'current_week_installments'   => [
                'count'      => $currentWeekInstallments->count(),
                'items'      => $currentWeekInstallments,
                'week_start' => $startOfWeek,
                'week_end'   => $endOfWeek,
            ],
            'recent_surveys'              => $recentSurveys,
            'unapproved_receipts'         => [
                'count' => $unapprovedReceiptsCount,
                'items' => $unapprovedReceiptsList,
            ],
            'pending_certificates'        => $pendingCertificatesCount,
            'quick_stats'                 => [
                'active_courses'            => $activeCoursesCount,
                'confirmed_registrations'   => $totalConfirmedRegistrations,
                'unapproved_receipts_count' => $unapprovedReceiptsCount,
                'pending_certificates'      => $pendingCertificatesCount,
            ],
        ]);
    }

    /**
     * Convert a date to Jalali (Shamsi) format using Verta.
     */
    private function toJalali($date, string $format = 'Y/m/d'): ?string
    {
        if (!$date) return null;
        try {
            $first4 = substr($date, 0, 4);
            if (is_numeric($first4)) {
                $year = (int) $first4;
                if ($year >= 1200 && $year <= 1500) {
                    $clean = str_replace('/', '-', $date);
                    $parts = explode('-', $clean);
                    if (count($parts) >= 3) {
                        return sprintf('%04d/%02d/%02d', (int)$parts[0], (int)$parts[1], (int)$parts[2]);
                    }
                }
            }
            return (new Verta($date))->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }
}
