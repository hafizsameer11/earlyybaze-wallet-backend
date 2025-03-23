<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WithdrawTransaction;
use App\Services\RefferalEarningService;
use Illuminate\Http\Request;

class RefferalManagementController extends Controller
{
    protected $refferalEarningService;
    public function __construct(RefferalEarningService $refferalEarningService)
    {
        $this->refferalEarningService = $refferalEarningService;
    }
    public function getRefferalManagement()
    {
        $users = User::with('userAccount')->get();

        $data = $users
            ->filter(function ($user) { // ✅ Remove users with zero referrals
                return User::where('invite_code', $user->user_code)->count() > 0;
            })
            ->map(function ($user) {
                $withdrawTransactions = WithdrawTransaction::whereHas('transaction', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->with('transaction')
                    ->get();

                $amountUsd = $withdrawTransactions->transaction->sum('amount_usd');
                $amountNaira = $withdrawTransactions->transaction->sum('amount');
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'referrals' => User::where('invite_code', $user->user_code)->count(),
                    'earned' => $user->userAccount->referral_earning_naira,
                    'usd' => $user->userAccount->total_referral_earnings,
                    'referrer' => $user->user_code,
                    'withdrawn' => $amountNaira,
                    'withdrawn_usd' => $amountUsd,
                    'img' => $user->profile_picture
                ];
            });

        return ResponseHelper::success($data->values(), "Data fetched successfully", 200);
    }
}
