<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Registertut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Hekmatinasser\Verta\Verta;

/**
 * Dedicated controller for detailed course statistics (analytics page).
 * Inspired by the old monolithic StatisticsController at
 * portal/app/Http/Controllers/Tuts/StatisticsController.php
 */
class CourseStatisticsController extends Controller
{
    /**
     * Get detailed statistics filtered by year (Jalali) and optional course_id.
     * Returns monthly, seasonal, yearly totals, and chart data.
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->input('year', (string) Verta::now()->year);
        $courseId = $request->input('course_id');

        // Helper: get Jalali month start/end as Gregorian datetimes
        $getMonthStart = function ($y, $m) {
            return Verta::parse("{$y}/{$m}/01")->startDay()->datetime();
        };
        $getMonthEnd = function ($y, $m) {
            return Verta::parse("{$y}/{$m}/01")->endMonth()->datetime();
        };

        // Effective price expression (must use DB::raw in SUM, not column alias)
        $effectivePriceExpr = "CASE WHEN registertuts.payment_method = 'online' THEN COALESCE(gateway_transactions.price, 0) ELSE CAST(COALESCE(courses.amount, 0) AS UNSIGNED) END";

        // Base query builder — starts from registertuts, LEFT JOIN to payments & transactions
        // This ensures bank receipt registrations (no gateway transaction) are included.
        // For online payments, amount = gateway_transactions.price.
        // For bank receipts, amount = courses.amount.
        $baseQuery = function ($startDate, $endDate, $cId) {
            $q = DB::table('registertuts')
                ->leftJoin('registertuts_payments', 'registertuts_payments.register_id', '=', 'registertuts.id')
                ->leftJoin('gateway_transactions', 'gateway_transactions.id', '=', 'registertuts_payments.transaction_id')
                ->join('courses', 'registertuts.course_id', '=', 'courses.id')
                ->select(
                    'gateway_transactions.id as transaction_id',
                    'gateway_transactions.status as gateway_status',
                    'gateway_transactions.price',
                    'gateway_transactions.created_at as transaction_created_at',
                    DB::raw('CASE WHEN registertuts.payment_method = \'online\' THEN COALESCE(gateway_transactions.price, 0) ELSE CAST(COALESCE(courses.amount, 0) AS UNSIGNED) END as effective_price'),
                    'registertuts.id as register_id',
                    'registertuts.payment_method',
                    'registertuts.verified_receipt',
                    'registertuts.course_id',
                    'registertuts.created_at as register_created_at'
                )
                ->where(function ($w) {
                    $w->where('gateway_transactions.status', 'SUCCEED')
                      ->orWhere('registertuts.verified_receipt', true);
                })
                ->whereBetween('registertuts.created_at', [$startDate, $endDate]);

            if ($cId) {
                $q->where('registertuts.course_id', $cId);
            }
            return $q;
        };

        // ===== Monthly Stats =====
        $months = [];
        $monthLabels = [];
        $chartRegistrations = [];

        for ($m = 1; $m <= 12; $m++) {
            $startDate = $getMonthStart($year, $m);
            $endDate = $getMonthEnd($year, $m);
            $bq = $baseQuery($startDate, $endDate, $courseId);

            $registeredCount = (clone $bq)->distinct('registertuts.id')->count('registertuts.id');
            $totalAmount = intval((clone $bq)->sum(DB::raw($effectivePriceExpr)));

            $onlinePayments = (clone $bq)
                ->where('registertuts.payment_method', 'online')
                ->where('gateway_transactions.status', 'SUCCEED')
                ->distinct('registertuts.id')
                ->count('registertuts.id');

            $bankPayments = (clone $bq)
                ->where('registertuts.payment_method', 'bank')
                ->where('registertuts.verified_receipt', true)
                ->distinct('registertuts.id')
                ->count('registertuts.id');

            $monthName = Verta::parse("{$year}/{$m}/01")->format('F');
            $monthLabels[] = $monthName;
            $chartRegistrations[] = $registeredCount;

            $months[] = [
                'month_id'        => $m,
                'month_name'      => $monthName,
                'registered_count'=> $registeredCount,
                'total_amount'    => intval($totalAmount),
                'online_payments' => $onlinePayments,
                'bank_payments'   => $bankPayments,
            ];
        }

        // ===== Seasonal Stats =====
        $seasons = [
            1 => ['name' => 'بهار', 'months' => [1, 2, 3]],
            2 => ['name' => 'تابستان', 'months' => [4, 5, 6]],
            3 => ['name' => 'پاییز', 'months' => [7, 8, 9]],
            4 => ['name' => 'زمستان', 'months' => [10, 11, 12]],
        ];
        $seasonalStats = [];
        foreach ($seasons as $sid => $season) {
            $sStart = $getMonthStart($year, $season['months'][0]);
            $sEnd   = $getMonthEnd($year, $season['months'][2]);
            $sbq = $baseQuery($sStart, $sEnd, $courseId);

            $seasonalStats[] = [
                'season_id'       => $sid,
                'name'            => $season['name'],
                'registered_count'=> (clone $sbq)->distinct('registertuts.id')->count('registertuts.id'),
                'total_amount'    => intval((clone $sbq)->sum(DB::raw($effectivePriceExpr))),
            ];
        }

        // ===== Yearly Stats (from 1400 to current) =====
        $currentYear = Verta::now()->year;
        $startYear = 1400;
        $yearlyStats = [];
        for ($y = $startYear; $y <= $currentYear; $y++) {
            $yStart = Verta::parse("{$y}/01/01")->startDay()->datetime();
            $yEnd   = Verta::parse("{$y}/12/29")->endMonth()->datetime();
            $ybq = $baseQuery($yStart, $yEnd, $courseId);

            $yearlyStats[] = [
                'year'            => $y,
                'registered_count'=> (clone $ybq)->distinct('registertuts.id')->count('registertuts.id'),
                'total_amount'    => intval((clone $ybq)->sum(DB::raw($effectivePriceExpr))),
            ];
        }
        // Sort descending (newest first)
        usort($yearlyStats, fn($a, $b) => $b['year'] - $a['year']);

        // ===== Totals (for selected year/course) =====
        $yearStart = $getMonthStart($year, 1);
        $yearEnd   = $getMonthEnd($year, 12);
        $tbq = $baseQuery($yearStart, $yearEnd, $courseId);

        $totalRegistered = (clone $tbq)->distinct('registertuts.id')->count('registertuts.id');
        $totalAmount     = intval((clone $tbq)->sum(DB::raw($effectivePriceExpr)));
        $totalOnline     = (clone $tbq)
            ->where('registertuts.payment_method', 'online')
            ->where('gateway_transactions.status', 'SUCCEED')
            ->distinct('registertuts.id')
            ->count('registertuts.id');
        $totalBank       = (clone $tbq)
            ->where('registertuts.payment_method', 'bank')
            ->where('registertuts.verified_receipt', true)
            ->distinct('registertuts.id')
            ->count('registertuts.id');

        // Peek month & average
        $avgMonthly = $totalRegistered > 0 ? round($totalRegistered / 12) : 0;
        $peekMonth = 'ندارد';
        $maxCount = 0;
        foreach ($months as $m) {
            if ($m['registered_count'] > $maxCount) {
                $maxCount = $m['registered_count'];
                $peekMonth = $m['month_name'];
            }
        }
        if ($maxCount > 0) {
            $peekMonth .= " با " . number_format($maxCount) . " نفر";
        }

        return response()->json([
            'data' => [
                'year'            => $year,
                'course_id'       => $courseId,
                'total_stats'     => [
                    'total_registered' => $totalRegistered,
                    'total_amount'     => $totalAmount,
                    'online_payments'  => $totalOnline,
                    'bank_payments'    => $totalBank,
                    'avg_monthly'      => $avgMonthly,
                    'peek_month'       => $peekMonth,
                ],
                'monthly'         => $months,
                'seasonal'        => $seasonalStats,
                'yearly'          => $yearlyStats,
                'chart_data'      => [
                    'months'        => $monthLabels,
                    'registrations' => $chartRegistrations,
                ],
            ],
        ]);
    }

    /**
     * Get aggregate statistics for courses and registrations.
     * Returns active course count, total registrations, verified count, and top courses.
     */
    public function statistics(): JsonResponse
    {
        $activeCourses = Course::where('active', true)->count();
        $totalRegistrations = Registertut::count();

        // Verified registrations (online paid + bank verified)
        $verifiedCount = Registertut::where(function ($q) {
                $q->where('verified_receipt', true)
                  ->orWhere(function ($q2) {
                      $q2->where('payment_method', 'online')
                         ->whereHas('payment.transaction', function ($q3) {
                             $q3->where('status', 'SUCCEED');
                         });
                  });
            })
            ->count();

        // Registration stats by course
        $topCourses = Course::withCount('registrations')
            ->orderBy('registrations_count', 'desc')
            ->take(5)
            ->get()
            ->map(function ($course) {
                return [
                    'id'    => $course->id,
                    'title' => $course->title,
                    'count' => $course->registrations_count,
                ];
            });

        return response()->json([
            'data' => [
                'active_courses'      => $activeCourses,
                'total_registrations' => $totalRegistrations,
                'verified_count'      => $verifiedCount,
                'top_courses'         => $topCourses,
            ],
        ]);
    }
}
