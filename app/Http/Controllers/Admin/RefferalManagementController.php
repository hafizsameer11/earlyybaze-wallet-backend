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
                    ->where('month', $monthKey) // e.g., '2025-05'
                    ->get();

                $latestPayout = $payouts->first();
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
                    'earned_usd' => $totalPaidUsd ?? 0,
                    'earned_naira' => $totalPaidNaira ?? 0,
                    'referrer' => $user->user_code,
                    'status' => $latestPayout->status ?? '',
                    'paidAt' => $latestPayout->paid_at ?? '',
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
    public function setExchangeRate(Request $request){
        $amount=   $request->input('amount');
        $user_id=   $request->input('user_id');
        $is_for_all=   $request->input('is_for_all', true);
        if($is_for_all){
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
