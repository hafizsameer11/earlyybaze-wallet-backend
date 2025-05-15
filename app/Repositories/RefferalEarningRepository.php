<?php

namespace App\Repositories;

use App\Models\BankAccount;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
use App\Models\SwapTransaction;
use App\Models\User;
use App\Models\UserAccount;
use Carbon\Carbon;

class RefferalEarningRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }
    public function getForUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $monthKey = Carbon::now()->format('Y-m');

        $referredUsers = User::where('invite_code', $user->user_code)->get();

        // Step 1: Create referral earnings based on swaps
        foreach ($referredUsers as $refUser) {
            $swaps = SwapTransaction::where('user_id', $refUser->id)
                ->where('status', 'success')
                ->where('amount_usd', '>=', 50)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->get();

            foreach ($swaps as $txn) {
                $alreadyExists = ReferalEarning::where('swap_transaction_id', $txn->id)
                    ->where('user_id', $id)
                    ->exists();

                if (!$alreadyExists) {
                    ReferalEarning::create([
                        'user_id' => $id,
                        'referal_id' => $refUser->id,
                        'amount' => 1,
                        'currency' => 'USD',
                        'type' => 'swap_bonus',
                        'status' => 'pending',
                        'swap_transaction_id' => $txn->id,
                        'created_at' => $txn->created_at,
                    ]);
                }
            }
        }

        // Step 2: Calculate totals
        $totalReferred = $referredUsers->count();

        // ✅ Filter earnings only from this month
        $pendingUsd = ReferalEarning::where('user_id', $id)
            ->where('status', 'pending')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $paidUsd = ReferalPayOut::where('user_id', $id)->where('status', 'paid')->sum('amount');
        $account = UserAccount::where('user_id', $id)->first();

        // Step 3: Create or update this month's payout record
        $payout = ReferalPayOut::firstOrCreate(
            [
                'user_id' => $id,
                'month' => $monthKey,
            ],
            [
                'status' => 'pending',
                'amount' => 0,
                'exchange_rate' => null,
                'paid_to_account' => $account->account_number ?? null,
                'paid_to_name' => $account->account_name ?? null,
                'paid_to_bank' => $account->bank_name ?? null,
            ]
        );

        // ✅ Only update amount if just created
        if ($payout->wasRecentlyCreated) {
            $payout->amount = $pendingUsd;
            $payout->save();
        }

        // Step 4: Prepare referred user stats
        $detailedReferrals = $referredUsers->map(function ($refUser) use ($id) {
            $earnedFromUser = ReferalEarning::where('user_id', $id)
                ->where('referal_id', $refUser->id)
                ->sum('amount');

            $subReferralCount = User::where('invite_code', $refUser->user_code)->count();

            return [
                'name' => $refUser->name,
                'amount' => $earnedFromUser,
                'created_at' => $refUser->created_at,
                'image' => $refUser->profile_picture,
                'refferalCount' => $subReferralCount,
                'totalEarning' => $earnedFromUser,
                'totalWithdrawls' => ReferalPayOut::where('user_id', $id)->sum('amount'),
                'noOfReferrals' => $subReferralCount,
                'totalTradesCompletedByReferrals' => SwapTransaction::where('user_id', $refUser->id)
                    ->where('status', 'success')
                    ->count(),
            ];
        });
        $userBanks = BankAccount::where('user_id', $id)->latest()->first();
        // Step 5: Final response
        return response()->json([
            'earning' => $detailedReferrals,
            'totalRefferals' => $totalReferred,
            'reffralCode' => $user->user_code,
            'Earning' => [
                'usd_pending' => $pendingUsd,
                'usd_paid' => $paidUsd,
                'naira_paid' => $account->referral_earning_naira ?? 0,
            ],
            'payout' => [
                'amount' => $payout->amount,
                'status' => $payout->status,
                'exchange_rate' => $payout->exchange_rate,
                'paid_to_account' => $payout->paid_to_account,
                'paid_to_name' => $payout->paid_to_name,
                'paid_to_bank' => $payout->paid_to_bank,
                'month' => $payout->month,
            ],
            'accountDetails' => $account,
            'BankAccount' => $userBanks
        ]);
    }
    public function find($id)
    {
        // Add logic to find data by ID
    }
    // public function getForUser($id)
    // {
    //     $user = User::find($id);

    //     if (!$user) {
    //         return response()->json(['error' => 'User not found'], 404);
    //     }
    //     $referredUsers = User::where('invite_code', $user->user_code)->get();
    //     $data = $referredUsers->map(function ($refUser) use ($id) {
    //         $amount = ReferalEarning::where('user_id', $id)
    //             ->where('referal_id', $refUser->id)
    //             ->sum('amount');

    //         // How many users this referred user invited
    //         $refferalCount = User::where('invite_code', $refUser->user_code)->count();

    //         return [
    //             'name' => $refUser->name,
    //             'amount' => $amount,
    //             'created_at' => $refUser->created_at,
    //             'image' => $refUser->profile_picture,
    //             'refferalCount' => $refferalCount,
    //             'totalEarning' => '0',
    //             'totalWithdrawls' => '0',
    //             'noOfReferrals' => $refferalCount,
    //             'totalTradesCompletedByReferrals' => '0',
    //         ];
    //     });

    //     // 3. Get user's referral balance
    //     $account = UserAccount::where('user_id', $id)->first();

    //     return [
    //         'earning' => $data,
    //         'totalRefferals' => $referredUsers->count(),
    //         'reffralCode' => $user->user_code,
    //         'Earning' => [
    //             'usd' => $account->total_referral_earnings ?? 0,
    //             'naira' => $account->referral_earning_naira ?? 0,
    //         ],
    //     ];
    // }


    public function create(array $data)
    {
        // Add logic to create data
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
