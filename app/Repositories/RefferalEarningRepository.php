<?php

namespace App\Repositories;

use App\Models\ReferalEarning;
use App\Models\User;
use App\Models\UserAccount;

class RefferalEarningRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }
    public function getForUser($id)
{
    $user = User::find($id);

    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // 1. Get all referred users (even if they don’t have earnings yet)
    $referredUsers = User::where('invite_code', $user->user_code)->get();

    // 2. Map each referred user to the desired format
    $data = $referredUsers->map(function ($refUser) use ($id) {
        // Total earnings from this referred user (optional)
        $amount = ReferalEarning::where('user_id', $id)
            ->where('referal_id', $refUser->id)
            ->sum('amount');

        // How many users this referred user invited
        $refferalCount = User::where('invite_code', $refUser->user_code)->count();

        return [
            'name' => $refUser->name,
            'amount' => $amount,
            'created_at' => $refUser->created_at,
            'image' => $refUser->profile_picture,
            'refferalCount' => $refferalCount,
            'totalEarning'=>'0',
            'totalWithdrawls'=>'0',
            'noOfReferrals'=>$refferalCount,
            'totalTradesCompletedByReferrals'=>'0',
        ];
    });

    // 3. Get user's referral balance
    $account = UserAccount::where('user_id', $id)->first();

    return [
        'earning' => $data,
        'totalRefferals' => $referredUsers->count(),
        'reffralCode' => $user->user_code,
        'Earning' => [
            'usd' => $account->total_referral_earnings ?? 0,
            'naira' => $account->referral_earning_naira ?? 0,
        ],
    ];
}


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
