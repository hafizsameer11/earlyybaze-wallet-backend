<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
use App\Models\ReferralExchangeRate;
use App\Models\ReferralWallet;
use App\Models\ReferralWalletTopUp;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\WithdrawTransaction;
use App\Services\RefferalEarningService;
use App\Services\ReferralEarningServiceNew;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        'swapTransaction:id,amount,amount_usd,currency'
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
            'swap_currency'    => $earning->swapTransaction->currency ?? null,
            'swap_amount'      => $earning->swapTransaction->amount ?? null,
            'commission_rate'  => abs((float) $earning->amount - 0.1) < 0.001 ? '$0.10 fixed' : '2.5%',
            'tx_ref'           => $earning->swap_transaction_id
                ? 'SWP'.strtoupper(substr(md5((string) $earning->swap_transaction_id), 0, 8))
                : 'ERN'.strtoupper(substr(md5((string) $earning->id), 0, 8)),
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
            'total_swapped_count' => $periodEarnings->whereNotNull('swap_transaction_id')->count(),
        ];
    }

    $earnerIds = $allEarnings->pluck('user_id')->unique()->values();
    $referrerUsers = User::where('role', 'user')
        ->where(function ($q) use ($earnerIds) {
            $q->whereIn('id', $earnerIds)
                ->orWhereNotNull('user_code');
        })
        ->get(['id', 'name', 'email', 'user_code', 'invite_code', 'is_active', 'referral_amount', 'created_at']);

    $usersByCode = User::whereNotNull('user_code')->get(['id', 'name', 'user_code'])->keyBy('user_code');

    $referrers = $referrerUsers->map(function ($user) use ($summaryByUser, $usersByCode) {
        $summary = $summaryByUser[$user->id] ?? [
            'total_earned_usd' => 0,
            'total_referrals'  => 0,
            'unique_referred'  => 0,
            'total_swapped'    => 0,
        ];
        $refCount = User::where('invite_code', $user->user_code)->count();
        $referredBy = $user->invite_code ? ($usersByCode[$user->invite_code]->name ?? null) : null;
        $referredById = $user->invite_code ? ($usersByCode[$user->invite_code]->id ?? null) : null;

        return [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'user_code'        => $user->user_code,
            'referral_count'   => max($refCount, (int) ($summary['unique_referred'] ?? 0)),
            'total_earned_usd' => (float) ($summary['total_earned_usd'] ?? 0),
            'commission'       => self::formatReferralCommission($user->referral_amount),
            'referral_amount'  => $user->referral_amount,
            'referred_by'      => $referredBy,
            'referred_by_id'   => $referredById,
            'tier'             => self::resolveReferralTier(max($refCount, (int) ($summary['unique_referred'] ?? 0))),
            'status'           => $user->is_active ? 'active' : 'suspended',
            'created_at'       => $user->created_at,
        ];
    })->sortByDesc('total_earned_usd')->values();

    $codes = User::whereNotNull('user_code')
        ->where('role', 'user')
        ->orderByDesc('created_at')
        ->get(['id', 'name', 'email', 'user_code', 'is_active', 'created_at'])
        ->map(function ($user) {
            $uses = User::where('invite_code', $user->user_code)->count();
            return [
                'user_id'    => $user->id,
                'name'       => $user->name,
                'code'       => $user->user_code,
                'uses'       => $uses,
                'status'     => $user->is_active ? 'active' : 'banned',
                'created_at' => $user->created_at,
            ];
        })
        ->values();

    $walletBalances = VirtualAccount::where('currency', 'USDT_TRON')
        ->get(['user_id', 'available_balance'])
        ->keyBy('user_id');

    $monthlyPayouts = ReferalPayOut::with('user:id,name,email')
        ->orderByDesc('created_at')
        ->limit(100)
        ->get()
        ->map(function ($payout) use ($walletBalances) {
            $mainBalance = $walletBalances->get($payout->user_id);

            return [
                'id'           => $payout->id,
                'user_id'      => $payout->user_id,
                'user_name'    => $payout->user->name ?? null,
                'amount'       => (float) $payout->amount,
                'status'       => $payout->status,
                'month'        => $payout->month,
                'tx_ref'       => 'PAY'.strtoupper(substr(md5($payout->id.$payout->month), 0, 8)),
                'main_balance' => $mainBalance ? (float) $mainBalance->available_balance : null,
                'payout_type'  => 'monthly',
                'paid_at'      => $payout->paid_at,
                'created_at'   => $payout->created_at,
            ];
        });

    $commissionTransfers = Transaction::with('user:id,name')
        ->where('transfer_type', 'referral_commission')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get()
        ->map(function ($tx) use ($walletBalances) {
            $mainBalance = $walletBalances->get($tx->user_id);

            return [
                'id'           => 'tx_'.$tx->id,
                'user_id'      => $tx->user_id,
                'user_name'    => $tx->user->name ?? null,
                'amount'       => (float) ($tx->amount_usd ?? $tx->amount),
                'status'       => 'paid',
                'month'        => $tx->created_at->format('Y-m'),
                'tx_ref'       => $tx->reference ?? ('PAY'.strtoupper(substr(md5((string) $tx->id), 0, 8))),
                'main_balance' => $mainBalance ? (float) $mainBalance->available_balance : null,
                'payout_type'  => 'commission_transfer',
                'currency'     => $tx->currency,
                'paid_at'      => $tx->created_at,
                'created_at'   => $tx->created_at,
            ];
        });

    $payouts = $monthlyPayouts->concat($commissionTransfers)
        ->sortByDesc(fn ($row) => $row['created_at'])
        ->values()
        ->take(100);

    $network = User::whereNotNull('invite_code')
        ->where('invite_code', '!=', '')
        ->get(['id', 'name', 'email', 'invite_code', 'created_at'])
        ->map(function ($user) use ($usersByCode) {
            $referrer = $usersByCode[$user->invite_code] ?? null;
            return [
                'user_id'       => $user->id,
                'user_name'     => $user->name,
                'referrer_id'   => $referrer->id ?? null,
                'referrer_name' => $referrer->name ?? null,
                'joined_at'     => $user->created_at,
            ];
        })
        ->values();

    $pendingTotal = (float) ReferalPayOut::where('status', 'pending')->sum('amount');
    $completedTotal = (float) ReferalPayOut::where('status', 'paid')->sum('amount');
    $failedTotal = (float) ReferalPayOut::where('status', 'failed')->sum('amount');

    return response()->json([
        'earnings' => $earnings,
        'stats'    => [
            'referrers'           => $summaryByUser->count(),
            'total_referrals'     => $allEarnings->count(),
            'total_swapped_count' => $allEarnings->whereNotNull('swap_transaction_id')->count(),
            'total_earned_usd'    => (float) $allEarnings->sum('amount'),
            'total_users'         => User::where('role', 'user')->count(),
            'active_users'        => User::where('role', 'user')->where('is_active', 1)->count(),
            'pending_payout_usd'  => $pendingTotal,
            'completed_payout_usd'=> $completedTotal,
            'failed_payout_usd'   => $failedTotal,
        ],
        'by_period' => $byPeriod,
        'referrers' => $referrers,
        'codes'     => $codes,
        'payouts'   => $payouts,
        'network'   => $network,
    ]);
}

private static function resolveReferralTier(int $referralCount): string
{
    if ($referralCount >= 50) {
        return 'Platinum';
    }
    if ($referralCount >= 25) {
        return 'Gold';
    }
    if ($referralCount >= 12) {
        return 'Silver';
    }

    return 'Bronze';
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

        return $this->approvePayoutRecord($payout);
    }

    public function approvePayout($id)
    {
        $payout = ReferalPayOut::find($id);
        if (! $payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        return $this->approvePayoutRecord($payout);
    }

    public function rejectPayout($id)
    {
        $payout = ReferalPayOut::find($id);
        if (! $payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        if ($payout->status === 'paid') {
            return response()->json(['error' => 'Cannot reject a completed payout'], 422);
        }

        $payout->status = 'failed';
        $payout->save();

        app(\App\Services\NotificationService::class)->notifyUser(
            (int) $payout->user_id,
            'Referral payout rejected',
            'Your referral payout request was rejected. Contact support if you need help.',
            'referral_payout'
        );

        return response()->json(['message' => 'Payout rejected successfully']);
    }

    private function approvePayoutRecord(ReferalPayOut $payout)
    {
        if ($payout->status === 'paid') {
            return response()->json(['message' => 'Payout already completed']);
        }

        $payout->status = 'paid';
        $payout->paid_at = now();
        $payout->save();

        app(ReferralEarningServiceNew::class)->settlePayout($payout);

        app(\App\Services\NotificationService::class)->notifyUser(
            (int) $payout->user_id,
            'Referral payout sent',
            'Your referral earnings have been marked as paid.',
            'referral_payout'
        );

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
                app(\App\Services\NotificationService::class)->notifyUser(
                    (int) $userId,
                    'Referral payout sent',
                    'Your referral earnings for this month have been marked as paid.',
                    'referral_payout'
                );
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

    public function updateUserReferralSettings(Request $request, int $userId)
    {
        $user = User::where('id', $userId)->where('role', 'user')->first();
        if (! $user) {
            return ResponseHelper::error('User not found', 404);
        }

        $validated = $request->validate([
            'user_code' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('users', 'user_code')->ignore($userId),
            ],
            'referral_amount' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        if (array_key_exists('user_code', $validated)
            && $validated['user_code']
            && $validated['user_code'] !== $user->user_code) {
            $oldCode = $user->user_code;
            if ($oldCode) {
                User::where('invite_code', $oldCode)->update(['invite_code' => $validated['user_code']]);
            }
            $user->user_code = $validated['user_code'];
        }

        if (array_key_exists('referral_amount', $validated)) {
            $user->referral_amount = $validated['referral_amount'] !== null && $validated['referral_amount'] !== ''
                ? (string) $validated['referral_amount']
                : null;
        }

        $user->save();

        return ResponseHelper::success([
            'id' => $user->id,
            'user_code' => $user->user_code,
            'referral_amount' => $user->referral_amount,
            'commission' => self::formatReferralCommission($user->referral_amount),
        ], 'Referral settings updated', 200);
    }

    public function regenerateUserReferralCode(int $userId)
    {
        $user = User::where('id', $userId)->where('role', 'user')->first();
        if (! $user) {
            return ResponseHelper::error('User not found', 404);
        }

        $base = preg_replace('/[^a-zA-Z0-9]/', '', (string) $user->name) ?: 'user';
        do {
            $code = strtolower($base).'-'.random_int(100000, 999999);
        } while (User::where('user_code', $code)->where('id', '!=', $user->id)->exists());

        $oldCode = $user->user_code;
        $user->user_code = $code;
        $user->save();

        if ($oldCode) {
            User::where('invite_code', $oldCode)->update(['invite_code' => $code]);
        }

        return ResponseHelper::success([
            'user_id' => $user->id,
            'user_code' => $code,
        ], 'Referral code regenerated', 200);
    }

    public function toggleReferralUserStatus(int $userId)
    {
        $user = User::where('id', $userId)->where('role', 'user')->first();
        if (! $user) {
            return ResponseHelper::error('User not found', 404);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return ResponseHelper::success([
            'user_id' => $user->id,
            'is_active' => $user->is_active,
            'status' => $user->is_active ? 'active' : 'banned',
        ], 'Referral user status updated', 200);
    }

    private static function formatReferralCommission(?string $amount): string
    {
        if ($amount !== null && $amount !== '' && is_numeric($amount) && (float) $amount > 0) {
            return '$'.number_format((float) $amount, 2).' per swap';
        }

        return '$0.10 default';
    }
}
