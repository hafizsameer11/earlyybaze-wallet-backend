<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
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


    public function getReferralManagementWithStats()
    {
        $monthKey = Carbon::now()->format('Y-m');
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $users = User::with('userAccount')->get();

        // === ADMIN TABLE DATA (PER USER) ===
        $managementData = $users
            ->filter(function ($user) {
                return User::where('invite_code', $user->user_code)->count() > 0;
            })
            ->map(function ($user) use ($monthKey) {
                $referrals = User::where('invite_code', $user->user_code)->count();

                $payouts = ReferalPayOut::where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->get();

                $totalPaidUsd = $payouts->sum('amount');

                $totalPaidNaira = $payouts->sum(function ($payout) {
                    return $payout->exchange_rate ? $payout->amount * $payout->exchange_rate : 0;
                });

                $currentMonthPayout = ReferalPayOut::where('user_id', $user->id)
                    ->where('month', $monthKey)
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'referrals' => $referrals,
                    'earned_usd' => $user->userAccount->total_referral_earnings ?? 0,
                    'earned_naira' => $user->userAccount->referral_earning_naira ?? 0,
                    'referrer' => $user->user_code,
                    'total_payout_usd' => $totalPaidUsd,
                    'total_payout_naira' => $totalPaidNaira,
                    'withdrawn_this_month_usd' => $currentMonthPayout?->amount ?? 0,
                    'withdrawn_this_month_naira' => $currentMonthPayout && $currentMonthPayout->exchange_rate
                        ? $currentMonthPayout->amount * $currentMonthPayout->exchange_rate
                        : 0,
                    'img' => $user->profile_picture,
                ];
            });

        // === DASHBOARD CARD STATS ===
        $referrersCount = User::whereIn('user_code', function ($query) {
            $query->select('invite_code')->from('users')->whereNotNull('invite_code');
        })->count();

        $totalReferrals = User::whereNotNull('invite_code')->count();

        $totalEarnings = ReferalEarning::sum('amount');

        $totalPaidUsd = ReferalPayOut::where('status', 'paid')->sum('amount');

        $totalPaidNaira = ReferalPayOut::where('status', 'paid')
            ->sum(DB::raw('amount * exchange_rate'));

        $pendingThisMonth = ReferalEarning::where('status', 'pending')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $payoutCountThisMonth = ReferalPayOut::where('month', $monthKey)->count();

        $stats = [
            'referrers' => $referrersCount,
            'total_referrals' => $totalReferrals,
            'total_earned_usd' => round($totalEarnings, 2),
            'total_paid_usd' => round($totalPaidUsd, 2),
            'total_paid_naira' => round($totalPaidNaira, 2),
            'pending_this_month_usd' => round($pendingThisMonth, 2),
            'payouts_this_month' => $payoutCountThisMonth,
        ];

        return response()->json([
            'stats' => $stats,
            'management' => $managementData->values(),
        ]);
    }
}
