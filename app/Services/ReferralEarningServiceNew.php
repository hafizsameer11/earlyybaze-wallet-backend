<?php 

namespace App\Services;

use App\Models\User;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
use App\Models\SwapTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralEarningServiceNew
{
    // your current rule: $0.10 per qualifying swap
    private const BONUS_USD = '0.1';
    private const MIN_SWAP_USD = 0;

    /**
     * Credit referral earning when a swap completes (idempotent).
     *
     * @throws \Throwable
     */
    public function creditOnSwapCompleted(SwapTransaction $swap): void
    {
        // only completed swaps qualify (use one canonical status everywhere)
        if ($swap->status !== 'completed') {
            return;
        }
        if ((float)$swap->amount_usd < self::MIN_SWAP_USD) {
            return;
        }

        // find the referrer (direct only) of the swap's user
        $swapUser = $swap->user; // assuming relation user() on SwapTransaction
        if (!$swapUser || !$swapUser->invite_code) {
            return; // no referrer → no credit
        }

        $referrer = User::where('user_code', $swapUser->invite_code)->first();
        if (!$referrer) {
            return;
        }

        DB::transaction(function () use ($swap, $referrer) {
            // 1) Idempotent create earning — guarded by unique index (user_id, swap_transaction_id)
            ReferalEarning::create([
                'user_id'             => $referrer->id,    // referrer
                'referal_id'          => $swap->user_id,   // referred (child) user
                'amount'              => self::BONUS_USD,  // USD
                'currency'            => 'USD',
                'type'                => 'swap_bonus',
                'status'              => 'pending',
                'swap_transaction_id' => $swap->id,
                'created_at'          => $swap->created_at, // preserves month alignment
                'updated_at'          => now(),
            ]);

            // 2) Keep current month payout row in sync (optional but nice UX)
            $monthKey     = Carbon::parse($swap->created_at)->format('Y-m');
            $startOfMonth = Carbon::parse($swap->created_at)->startOfMonth();
            $endOfMonth   = Carbon::parse($swap->created_at)->endOfMonth();

            // current pending sum for this referrer in that month
            $pendingUsd = ReferalEarning::where('user_id', $referrer->id)
                ->where('status', 'pending')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            // upsert payout IF there is something to pay; never touch already-paid rows
            $payout = ReferalPayOut::where('user_id', $referrer->id)
                ->where('month', $monthKey)
                ->first();

            if ($pendingUsd > 0) {
                if (!$payout) {
                    ReferalPayOut::create([
                        'user_id'        => $referrer->id,
                        'month'          => $monthKey,
                        'status'         => 'pending',
                        'amount'         => $pendingUsd,
                        // exchange_rate snapshot will be set at pay time
                        'exchange_rate'  => null,
                        // you can snapshot pay-to details here if you like
                        'paid_to_account'=> optional($referrer->userAccount)->account_number,
                        'paid_to_name'   => optional($referrer->userAccount)->account_name,
                        'paid_to_bank'   => optional($referrer->userAccount)->bank_name,
                    ]);
                } elseif ($payout->status !== 'paid') {
                    $payout->amount = $pendingUsd; // keep in sync as earnings accrue
                    $payout->save();
                }
            }
        });
    }

    /**
     * When admin marks a payout as paid, also flip underlying earnings to 'paid'
     * and link them to that payout (optional but strongly recommended).
     */
    public function settlePayout(ReferalPayOut $payout): void
    {
        $month    = $payout->month;                 // 'YYYY-MM'
        $userId   = $payout->user_id;
        $monthC   = Carbon::createFromFormat('Y-m', $month);
        $start    = $monthC->copy()->startOfMonth();
        $end      = $monthC->copy()->endOfMonth();

        DB::transaction(function () use ($payout, $userId, $start, $end) {
            ReferalEarning::where('user_id', $userId)
                ->where('status', 'pending')
                ->whereBetween('created_at', [$start, $end])
                ->update([
                    'status'    => 'paid',
                    'payout_id' => $payout->id,
                    'updated_at'=> now(),
                ]);
        });
    }
}
