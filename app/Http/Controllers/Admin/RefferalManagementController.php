<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
use App\Models\ReferralExchangeRate;
use App\Models\User;
use App\Models\WithdrawTransaction;
use App\Services\RefferalEarningService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefferalManagementController extends Controller
{
    protected $refferalEarningService;
    public function __construct(RefferalEarningService $refferalEarningService)
    {
        $this->refferalEarningService = $refferalEarningService;
    }


  public function getRefferalManagement()
{
    // ---- Period anchors
    $now            = Carbon::now();
    $todayStart     = $now->copy()->startOfDay();
    $todayEnd       = $now->copy()->endOfDay();

    $thisMonthStart = $now->copy()->startOfMonth();
    $thisMonthEnd   = $now->copy()->endOfMonth();
    $thisMonthKey   = $now->format('Y-m');

    $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
    $lastMonthEnd   = $now->copy()->subMonthNoOverflow()->endOfMonth();
    $lastMonthKey   = $now->copy()->subMonthNoOverflow()->format('Y-m');

    $thisYearStart  = $now->copy()->startOfYear();
    $thisYearEnd    = $now->copy()->endOfYear();

    // Keep your original vars for existing "stats"
    $startOfMonth = $thisMonthStart;
    $endOfMonth   = $thisMonthEnd;
    $monthKey     = $thisMonthKey;

    $users = \App\Models\User::with('userAccount')->get();

    // === ADMIN TABLE DATA (PER USER) — unchanged logic, just using $monthKey ===
    $managementData = $users
        ->filter(function ($user) {
            // Only users who have actually referred at least one user
            return \App\Models\User::where('invite_code', $user->user_code)->count() > 0;
        })
        ->map(function ($user) use ($monthKey) {
            $referrals = \App\Models\User::where('invite_code', $user->user_code)->count();

            // Current month payout row (any status)
            $currentMonthPayout = \App\Models\ReferalPayOut::where('user_id', $user->id)
                ->where('month', $monthKey)
                ->orderByDesc('id')
                ->first();

            $currentStatus = $currentMonthPayout->status ?? '';
            $currentPaidAt = ($currentMonthPayout && $currentMonthPayout->status === 'paid')
                ? $currentMonthPayout->paid_at
                : null;

            // Paid totals this month
            $paidThisMonth = \App\Models\ReferalPayOut::where('user_id', $user->id)
                ->where('month', $monthKey)
                ->where('status', 'paid')
                ->get();

            $totalPaidUsd = $paidThisMonth->sum('amount');
            $totalPaidNaira = $paidThisMonth->sum(function ($payout) {
                return $payout->exchange_rate ? ($payout->amount * $payout->exchange_rate) : 0;
            });

            // Pending totals this month
            $pendingThisMonth = \App\Models\ReferalPayOut::where('user_id', $user->id)
                ->where('month', $monthKey)
                ->where('status', 'pending')
                ->get();

            $pendingUsd = $pendingThisMonth->sum('amount');
            $pendingNaira = $pendingThisMonth->sum(function ($payout) {
                return $payout->exchange_rate ? ($payout->amount * $payout->exchange_rate) : 0;
            });

            // Withdrawn (only if current payout is actually paid)
            $withdrawnUsd = ($currentMonthPayout && $currentMonthPayout->status === 'paid')
                ? $currentMonthPayout->amount
                : 0;

            $withdrawnNaira = ($currentMonthPayout && $currentMonthPayout->status === 'paid' && $currentMonthPayout->exchange_rate)
                ? $currentMonthPayout->amount * $currentMonthPayout->exchange_rate
                : 0;

            return [
                'id'   => $user->id,
                'name' => $user->name,
                'referrals' => $referrals,

                'earned_usd' => $totalPaidUsd ?? 0,
                'earned_naira' => $totalPaidNaira ?? 0,

                'pending_payout_usd' => $pendingUsd ?? 0,
                'pending_payout_naira' => $pendingNaira ?? 0,

                'status' => $currentStatus,
                'paidAt' => $currentPaidAt,

                'total_payout_usd' => $totalPaidUsd,
                'total_payout_naira' => $totalPaidNaira,

                'withdrawn_this_month_usd' => $withdrawnUsd,
                'withdrawn_this_month_naira' => $withdrawnNaira,

                'referrer' => $user->user_code,
                'img' => $user->profile_picture,
            ];
        });

    // === EXISTING DASHBOARD CARDS (overall + this month) ===
    $referrersCount = \App\Models\User::whereIn('user_code', function ($query) {
        $query->select('invite_code')->from('users')->whereNotNull('invite_code');
    })->count();

    $totalReferrals = \App\Models\User::whereNotNull('invite_code')->count();

    $totalEarnings = \App\Models\ReferalEarning::sum('amount');

    $totalPaidUsd = \App\Models\ReferalPayOut::where('status', 'paid')->sum('amount');

    $totalPaidNaira = \App\Models\ReferalPayOut::where('status', 'paid')
        ->sum(DB::raw('amount * IFNULL(exchange_rate, 0)'));

    $pendingThisMonth = \App\Models\ReferalEarning::where('status', 'pending')
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->sum('amount');

    $payoutCountThisMonth = \App\Models\ReferalPayOut::where('month', $monthKey)->count();

    $stats = [
        'referrers'               => $referrersCount,
        'total_referrals'         => $totalReferrals,
        'total_earned_usd'        => round($totalEarnings, 2),
        'total_paid_usd'          => round($totalPaidUsd, 2),
        'total_paid_naira'        => round($totalPaidNaira, 2),
        'pending_this_month_usd'  => round($pendingThisMonth, 2),
        'payouts_this_month'      => $payoutCountThisMonth,
    ];

    // === NEW: PERIODIZED STATS ===
    $periods = [
        'today' => [
            'start' => $todayStart, 'end' => $todayEnd,
            'monthKey' => $now->format('Y-m'), // not used for count, here for consistency
        ],
        'this_month' => [
            'start' => $thisMonthStart, 'end' => $thisMonthEnd,
            'monthKey' => $thisMonthKey,
        ],
        'last_month' => [
            'start' => $lastMonthStart, 'end' => $lastMonthEnd,
            'monthKey' => $lastMonthKey,
        ],
        'this_year' => [
            'start' => $thisYearStart, 'end' => $thisYearEnd,
            'monthKey' => null, // year not tied to monthKey
        ],
    ];

    $byPeriod = [];
    foreach ($periods as $label => $p) {
        $start = $p['start'];
        $end   = $p['end'];

        // Earnings (by created_at)
        $earnedUsd = \App\Models\ReferalEarning::whereBetween('created_at', [$start, $end])
            ->sum('amount');

        // Pending earnings (by created_at)
        $pendingUsdPeriod = \App\Models\ReferalEarning::where('status', 'pending')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        // Paid payouts (by paid_at)
        $paidUsd = \App\Models\ReferalPayOut::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $paidNaira = \App\Models\ReferalPayOut::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum(DB::raw('amount * IFNULL(exchange_rate, 0)'));

        // Count of users who used an invite code in this period
        $referralsCount = \App\Models\User::whereNotNull('invite_code')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Payouts count (paid) by paid_at (universal)
        $payoutsCount = \App\Models\ReferalPayOut::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->count();

        // For month views, also add the legacy monthKey-based count (creation-insensitive)
        $payoutsByMonthKey = null;
        if (in_array($label, ['this_month', 'last_month'], true) && $p['monthKey']) {
            $payoutsByMonthKey = \App\Models\ReferalPayOut::where('month', $p['monthKey'])->count();
        }

        $byPeriod[$label] = [
            'total_earned_usd'    => round((float)$earnedUsd, 2),
            'total_paid_usd'      => round((float)$paidUsd, 2),
            'total_paid_naira'    => round((float)$paidNaira, 2),
            'pending_usd'         => round((float)$pendingUsdPeriod, 2),
            'referrals_count'     => $referralsCount,
            'payouts_count'       => $payoutsCount,          // by paid_at
            'payouts_by_month_key'=> $payoutsByMonthKey,     // only for month scopes
        ];
    }

    return response()->json([
        'stats'      => $stats,          // existing overall cards (unchanged)
        'by_period'  => $byPeriod,       // NEW: today / this_month / last_month / this_year
        'management' => $managementData->values(), // per-user table stays same
    ]);
}



    public function markAsPaid($userId)
    {
        $monthKey = Carbon::now()->format('Y-m');

        $payout = ReferalPayOut::where('user_id', $userId)
            ->where('month', $monthKey)
            ->first();

        if (!$payout) {
            return response()->json(['error' => 'Payout not found for current month'], 404);
        }

        $payout->status = 'paid';
        $payout->paid_at = now();
        $payout->save();

        return response()->json(['message' => 'Payout marked as paid successfully']);
    }

    public function markAsPaidBulk(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $monthKey = Carbon::now()->format('Y-m');

        $updated = 0;
        foreach ($request->user_ids as $userId) {
            $payout = ReferalPayOut::where('user_id', $userId)
                ->where('month', $monthKey)
                ->first();

            if ($payout && $payout->status !== 'paid') {
                $payout->status = 'paid';
                $payout->paid_at = now();
                $payout->save();
                $updated++;
            }
        }

        return response()->json([
            'message' => 'Bulk update completed',
            'total_marked_as_paid' => $updated,
        ]);
    }
    public function setExchangeRate(Request $request)
    {
        $amount =   $request->input('amount');
        $user_id =   $request->input('user_id');
        $is_for_all =   $request->input('is_for_all', true);
        if ($is_for_all) {
            // Update or create a global exchange rate
            $exchangeRate = ReferralExchangeRate::updateOrCreate(
                ['is_for_all' => true],
                ['amount' => $amount, 'user_id' => null]
            );
        } else {
            // Update or create a user-specific exchange rate
            $exchangeRate = ReferralExchangeRate::updateOrCreate(
                ['user_id' => $user_id, 'is_for_all' => false],
                ['amount' => $amount]
            );
        }
        return response()->json([
            'message' => 'Exchange rate set successfully',
            'exchange_rate' => $exchangeRate,
        ]);
    }
}
