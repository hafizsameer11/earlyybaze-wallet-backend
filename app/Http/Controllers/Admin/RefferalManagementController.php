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

    // Monthly keys
    $thisMonthKey = $now->format('Y-m');
    $lastMonthKey = $now->copy()->subMonthNoOverflow()->format('Y-m');

    $periods = [
        'this_month' => [
            'start' => $now->copy()->startOfMonth(),
            'end'   => $now->copy()->endOfMonth(),
            'monthKey' => $thisMonthKey,
        ],
        'last_month' => [
            'start' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
            'end'   => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            'monthKey' => $lastMonthKey,
        ],
        'this_year' => [
            'start' => $now->copy()->startOfYear(),
            'end'   => $now->copy()->endOfYear(),
            'monthKey' => null,
        ],
    ];

    // === RAW EARNINGS LIST ===
    $earnings = \App\Models\ReferalEarning::with(['user:id,name', 'referal:id,name', 'swapTransaction:id,amount,amount_usd'])
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($earning) {
            return [
                'id'             => $earning->id,
                'earner_name'    => $earning->user->name ?? null,
                'referred_name'  => $earning->referal->name ?? null,
                'amount_usd'     => $earning->amount,
                'status'         => $earning->status,
                'swap_id'        => $earning->swap_transaction_id,
                'swapped_amount' => $earning->swapTransaction->amount_usd ?? $earning->swapTransaction->amount,
                'created_at'     => $earning->created_at,
                'month_key'      => $earning->created_at->format('Y-m'),
            ];
        });

    // === SUMMARY BY swap_transaction_id ===
    $summaryBySwap = \App\Models\ReferalEarning::select(
            'swap_transaction_id',
            DB::raw('SUM(amount) as amount_usd'),
            DB::raw('COUNT(id) as earnings_count')
        )
        ->groupBy('swap_transaction_id')
        ->get();

    // === PERIOD STATS ===
    $byPeriod = [];
    foreach ($periods as $label => $p) {
        $start = $p['start'];
        $end   = $p['end'];

        $byPeriod[$label] = [
            'monthKey'       => $p['monthKey'],
            'total_earned_usd' => (float)\App\Models\ReferalEarning::whereBetween('created_at', [$start, $end])->sum('amount'),
            'pending_usd'    => (float)\App\Models\ReferalEarning::where('status', 'pending')
                                    ->whereBetween('created_at', [$start, $end])
                                    ->sum('amount'),
            'records_count'  => \App\Models\ReferalEarning::whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    // === OVERALL SUMMARY STATS FOR CARDS ===
    $stats = [
        'referrers'        => \App\Models\User::whereIn('id', \App\Models\ReferalEarning::pluck('user_id'))->count(),
        'total_referrals'  => \App\Models\ReferalEarning::count(),
        'total_earned_usd' => (float)\App\Models\ReferalEarning::sum('amount'),
        'pending_usd'      => (float)\App\Models\ReferalEarning::where('status', 'pending')->sum('amount'),
    ];

    return response()->json([
        'stats'         => $stats,       // ✅ for summary cards
        'earnings'      => $earnings,    // ✅ table data
        'by_period'     => $byPeriod,    // ✅ period summary
        'by_swap'       => $summaryBySwap, // ✅ transaction grouping
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
