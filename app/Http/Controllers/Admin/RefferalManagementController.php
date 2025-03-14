<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
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
            return [
                'id' => $user->id,
                'name' => $user->name,
                'referrals' => User::where('invite_code', $user->user_code)->count(),
                'earned' => $user->userAccount->referral_earning_naira,
                'usd' => $user->userAccount->total_referral_earnings,
                'referrer' => $user->user_code,
                'img' => $user->profile_picture
            ];
        });

    return ResponseHelper::success($data->values(), "Data fetched successfully", 200);
}

}
