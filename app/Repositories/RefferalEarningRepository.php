<?php

namespace App\Repositories;

use App\Models\BankAccount;
use App\Models\ReferalEarning;
use App\Models\ReferalPayOut;
use App\Models\SwapTransaction;
use App\Models\User;
use App\Models\UserAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        Log::info("current reffered user", [$referredUsers]);
        // Step 1: Create referral earnings based on swaps
        foreach ($referredUsers as $refUser) {
            $swaps = SwapTransaction::where('user_id', $refUser->id)
                ->where('status', 'completed')
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

        $totalReferred = $referredUsers->count();

        // Current month pending USD
        $pendingUsd = ReferalEarning::where('user_id', $id)
            ->where('status', 'pending')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $paidUsd = ReferalPayOut::where('user_id', $id)->where('status', 'paid')->sum('amount');

        $account = UserAccount::where('user_id', $id)->first();
        // $userBanks
        // Step 2: Get or create current month's payout
        $currentPayout = ReferalPayOut::firstOrCreate(
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

        if ($currentPayout->wasRecentlyCreated) {
            $currentPayout->amount = $pendingUsd;
            $currentPayout->save();
        }

        // Step 3: All payout history (including current)
        $payoutHistory = ReferalPayOut::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Step 4: Prepare referral breakdown
        $detailedReferrals = $referredUsers->map(function ($refUser) use ($id) {
            // Total earnings from this referred user
            $earnedFromUser = ReferalEarning::where('user_id', $id)
                ->where('referal_id', $refUser->id)
                ->sum('amount');

            // All earnings breakdowns from this user
            $referralEarnings = ReferalEarning::where('user_id', $id)
                ->where('referal_id', $refUser->id)
                ->get(['id', 'amount', 'status', 'type', 'created_at', 'swap_transaction_id']);

            // Total trades completed by this user (swap txn)
            $totalSwaps = SwapTransaction::where('user_id', $refUser->id)
                ->where('status', 'success')
                ->count();

            $subReferralCount = User::where('invite_code', $refUser->user_code)->count();

            return [
                'name' => $refUser->name,
                'image' => $refUser->profile_picture,
                'created_at' => $refUser->created_at,
                'amount' => $earnedFromUser,
                'totalEarning' => $earnedFromUser,
                'refferalCount' => $subReferralCount,
                'noOfReferrals' => $subReferralCount,
                'totalTradesCompletedByReferrals' => $totalSwaps,
                'totalWithdrawls' => ReferalPayOut::where('user_id', $id)->sum('amount'),

                // ✅ NEW:
                'referralEarningBreakdown' => $referralEarnings, // full list of earnings
            ];
        });


        $userBanks = BankAccount::where('user_id', $id)->latest()->first();

        // Step 5: Return everything
        return [
            'earning' => $detailedReferrals,
            'totalRefferals' => $totalReferred,
            'reffralCode' => $user->user_code,
            'Earning' => [
                'usd_pending' => $pendingUsd,
                'usd_paid' => $paidUsd,
                'naira_paid' => $account->referral_earning_naira ?? 0,
            ],
            'currentPayout' => [
                'amount' => $currentPayout->amount,
                'status' => $currentPayout->status,
                'exchange_rate' => $currentPayout->exchange_rate,
                'paid_to_account' => $currentPayout->paid_to_account,
                'paid_to_name' => $currentPayout->paid_to_name,
                'paid_to_bank' => $currentPayout->paid_to_bank,
            ],
            'payoutHistory' => $payoutHistory,
            'accountDetails' => $account,
            'bankAccount' => $userBanks,
        ];
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
