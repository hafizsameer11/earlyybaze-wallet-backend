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
        $totalRefferals = User::where('invite_code', $user->user_code)->count();
        $totalRefferlBalance = UserAccount::where('user_id', $id)->first();
        $data = ReferalEarning::where('user_id', $id)->with('referal')->get();
        $data = $data->map(function ($item) {
            return [
                'name' => $item->referal->name,
                'amount' => $item->amount,
                'created_at' => $item->created_at,
                'image' => $item->referal->profile_picture,
                'refferalCount' => $item->referal->count()

            ];
        });
        return [
            'earning' => $data,
            'totalRefferals' => $totalRefferals,
            'reffralCode' => $user->user_code,
            'Earning' => [
                'usd' => $totalRefferlBalance->total_referral_earnings,
                'naira' => $totalRefferlBalance->referral_earning_naira,
            ]
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
