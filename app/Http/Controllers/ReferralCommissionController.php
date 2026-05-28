<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\ReferralCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralCommissionController extends Controller
{
    public function __construct(private ReferralCommissionService $referralCommissionService) {}

    public function transferOptions()
    {
        try {
            $data = $this->referralCommissionService->getTransferOptions(Auth::id());

            return ResponseHelper::success($data, 'Transfer options retrieved successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|max:64',
        ]);

        try {
            $data = $this->referralCommissionService->transfer(
                Auth::id(),
                (string) $validated['amount'],
                $validated['currency']
            );

            return ResponseHelper::success($data, 'Commission transferred successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
