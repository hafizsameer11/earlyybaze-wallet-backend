<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\ReferalEarning;
use App\Models\User;
use App\Services\RefferalEarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefferalEarningController extends Controller
{
    protected $refferalEarningService;
    public function __construct(RefferalEarningService $refferalEarningService)
    {
        $this->refferalEarningService = $refferalEarningService;
    }
    public function getForAuthUser()
    {
        try {
            $user = Auth::user();
            $data = $this->refferalEarningService->getByUserId($user->id);
            return ResponseHelper::success($data, "Data fetched successfully", 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getUserReferralSummary()
{
    $userId=Auth::user()->id;
    $user = User::find($userId);
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // Fetch all referral earnings for this user
    $refEarnings = ReferalEarning::with([
        'referal:id,name,profile_picture',
        'swapTransaction:id,amount,amount_usd,status,created_at'
    ])
        ->where('referal_id', $userId)
        ->orderByDesc('created_at')
        ->get();

    if ($refEarnings->isEmpty()) {
        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_code' => $user->user_code,
            'referral_summary' => [
                'total_earned_usd' => 0,
                'total_referrals' => 0,
                'total_swaps' => 0,
                'referred_users' => [],
            ]
        ]);
    }

    // Totals
    $totalEarnedUsd = (float) $refEarnings->sum('amount');
    $uniqueReferred = $refEarnings->pluck('referal_id')->unique()->count();
    $totalSwaps = $refEarnings->whereNotNull('swap_transaction_id')->count();

    // Group by referred user
    $groupedReferrals = $refEarnings->groupBy('referal_id')->map(function ($items, $refId) {
        $first = $items->first();
        $totalEarnedFromUser = (float) $items->sum('amount');
        $totalSwapsFromUser = $items->whereNotNull('swap_transaction_id')->count();

        return [
            'referal_id'   => $refId,
            'referal_name' => $first->referal->name ?? 'Unknown',
            'referal_image'=> $first->referal->profile_picture ?? null,
            'total_earned' => $totalEarnedFromUser,
            'total_swaps'  => $totalSwapsFromUser,

            // Breakdown: show which user swapped (from whom he earned)
            'breakdown'    => $items->map(function ($e) {
                $refUser = $e->referal;
                $swap = $e->swapTransaction;

                return [
                    'earning_id'        => $e->id,
                    'amount_usd'        => (float) $e->amount,
                    'status'            => $e->status,
                    'type'              => $e->type,
                    'created_at'        => $e->created_at,

                    // swap transaction info
                    'swap_transaction'  => [
                        'id'             => $swap->id ?? null,
                        'amount'         => $swap->amount ?? null,
                        'amount_usd'     => $swap->amount_usd ?? null,
                        'status'         => $swap->status ?? null,
                        'created_at'     => $swap->created_at ?? null,
                    ],

                    // user who generated this earning
                    'from_user' => [
                        'id'        => $refUser->id ?? null,
                        'name'      => $refUser->name ?? 'Unknown',
                        'image'     => $refUser->profile_picture ?? null,
                    ]
                ];
            })->values(),
        ];
    })->values();

    // Final response
    return response()->json([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'referral_summary' => [
            'total_earned_usd' => $totalEarnedUsd,
            'total_referrals'  => $uniqueReferred,
            'total_swaps'      => $totalSwaps,
            'referred_users'   => $groupedReferrals,
        ]
    ]);
}

    public function getForUser($userId)
    {
        try {
            // $user = Auth::user();
            $data = $this->refferalEarningService->getByUserId($userId);
            return ResponseHelper::success($data, "Data fetched successfully", 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
