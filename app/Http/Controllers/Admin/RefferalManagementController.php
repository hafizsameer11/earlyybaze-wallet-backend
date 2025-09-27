<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
use App\Models\ReferralExchangeRate;
use App\Models\ReferralWallet;
use App\Models\ReferralWalletTopUp;
use App\Models\User;
use App\Models\WithdrawTransaction;
use App\Services\RefferalEarningService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    $now = Carbon::now();

    $periods = [
        'this_month' => [
            'start'    => $now->copy()->startOfMonth(),
            'end'      => $now->copy()->endOfMonth(),
            'monthKey' => $now->format('Y-m'),
        ],
        'last_month' => [
            'start'    => $now->copy()->subMonthNoOverflow()->startOfMonth(),
            'end'      => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            'monthKey' => $now->copy()->subMonthNoOverflow()->format('Y-m'),
        ],
        'this_year' => [
            'start'    => $now->copy()->startOfYear(),
            'end'      => $now->copy()->endOfYear(),
            'monthKey' => null,
        ],
    ];

    // preload all earnings with relations
    $allEarnings = \App\Models\ReferalEarning::with([
        'user:id,name',
        'referal:id,name',
        'swapTransaction:id,amount,amount_usd'
    ])
    ->orderByDesc('created_at')
    ->get();

    // build per-user summary
    $summaryByUser = $allEarnings->groupBy('user_id')->map(function ($rows) {
        $totalEarned    = $rows->sum('amount');
        $totalReferrals = $rows->count();
        $uniqueReferred = $rows->pluck('referal_id')->unique()->count();
        $totalSwapped   = $rows->sum(function ($e) {
            return $e->swapTransaction->amount_usd ?? $e->swapTransaction->amount ?? 0;
        });

        // build referred users list
        $referredUsers = $rows->map(function ($e) {
            return [
                'referred_id'    => $e->referal_id,
                'referred_name'  => $e->referal->name ?? '',
                'swap_id'        => $e->swap_transaction_id,
                'swapped_amount' => $e->swapTransaction->amount_usd ?? $e->swapTransaction->amount ?? 0,
                'earned_amount'  => $e->amount,
                'created_at'     => $e->created_at,
            ];
        });

        return [
            'total_earned_usd' => (float) $totalEarned,
            'total_referrals'  => (int) $totalReferrals,
            'unique_referred'  => (int) $uniqueReferred,
            'total_swapped'    => (float) $totalSwapped,
            'referred_users'   => $referredUsers->values(),
        ];
    });

    // attach summary data into each earning row
    $earnings = $allEarnings->map(function ($earning) use ($summaryByUser) {
        $summary = $summaryByUser[$earning->user_id] ?? [
            'total_earned_usd' => 0,
            'total_referrals'  => 0,
            'unique_referred'  => 0,
            'total_swapped'    => 0,
            'referred_users'   => [],
        ];

        return [
            'id'               => $earning->id,
            'earner_id'        => $earning->user_id,
            'earner_name'      => $earning->user->name ?? null,
            'referred_id'      => $earning->referal_id,
            'referred_name'    => $earning->referal->name ?? null,
            'amount_usd'       => $earning->amount,
            'status'           => $earning->status,
            'swap_id'          => $earning->swap_transaction_id,
            'swapped_amount'   => $earning->swapTransaction->amount_usd ?? $earning->swapTransaction->amount,
            'created_at'       => $earning->created_at,
            'month_key'        => $earning->created_at->format('Y-m'),

            // summary attached
            'total_earned_usd' => $summary['total_earned_usd'],
            'total_referrals'  => $summary['total_referrals'],
            'unique_referred'  => $summary['unique_referred'],
            'total_swapped'    => $summary['total_swapped'],
            'referred_users'   => $summary['referred_users'],
        ];
    });

    // build per-period stats including total_swapped_count
    $byPeriod = [];
    foreach ($periods as $label => $p) {
        $start = $p['start'];
        $end   = $p['end'];

        // preload earnings for this period
        $periodEarnings = \App\Models\ReferalEarning::with('swapTransaction')
                            ->whereBetween('created_at', [$start, $end])
                            ->get();

        $byPeriod[$label] = [
            'start'               => $start,
            'end'                 => $end,
            'monthKey'            => $p['monthKey'],
            'total_referrals'     => $periodEarnings->count(),
            'total_earned_usd'    => (float) $periodEarnings->sum('amount'),
            'unique_referrers'    => $periodEarnings->pluck('user_id')->unique()->count(),
            'total_swapped'       => (float) $periodEarnings->sum(function ($e) {
                                        return $e->swapTransaction->amount_usd ?? $e->swapTransaction->amount ?? 0;
                                    }),
            // ✅ Count of swap transactions within this period
            'total_swapped_count' => $periodEarnings->whereNotNull('swap_transaction_id')->count(),
        ];
    }

    return response()->json([
        'earnings' => $earnings,
        'stats'    => [
            'referrers'          => $summaryByUser->count(),
            'total_referrals'    => $allEarnings->count(),
            'total_swapped_count'=> $allEarnings->whereNotNull('swap_transaction_id')->count(),
            'total_earned_usd'   => (float) $allEarnings->sum('amount'),
        ],
        'by_period' => $byPeriod,
    ]);
}



  public function topUpRefferalWallet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = $request->input('amount');
        $userId = Auth::id();

        $walletTopup = ReferralWalletTopUp::create([
            'user_id' => $userId,
            'amount' => $amount,
            'status' => 'completed',
        ]);

        $wallet = ReferralWallet::first();
        if (!$wallet) {
            $wallet = ReferralWallet::create([
                'title' => 'Referral Wallet',
                'amount' => 0,
            ]);
        }

        $wallet->amount += $amount;
        $wallet->save();

        return response()->json([
            'message' => 'Wallet topup created successfully',
            'wallet' => $wallet,
            'wallet_topup' => $walletTopup,
        ]);
    }

    public function referralwalletBalance()
    {
        $wallet = ReferralWallet::first();
        $history = ReferralWalletTopUp::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'wallet' => $wallet,
            'history' => $history,
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
