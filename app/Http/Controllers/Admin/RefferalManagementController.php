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
        $data = $users->map(function ($user) {
            $refferal = User::where('invite_code', $user->user_code)->count();
            if ($refferal < 1) {
                return null;
            }
            return [
                'id' => $user->id,
                'name' => $user->name,
                'referrals' => $refferal,
                'earned' => $user->referral_earning_naira,
                'usd' => $user->total_referral_earnings,
                'referrer' => $user->user_code,
                'img' => $user->profile_picture
            ];
        });
        return ResponseHelper::success($data, "Data fetched successfully", 200);
    }
}
