<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Helpers\UserActivityHelper;
use App\Http\Requests\KycRequest;
use App\Models\Kyc;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KycController extends Controller
{
    protected $kycService;
    public function __construct(KycService $kycService)
    {
        $this->kycService = $kycService;
    }
    public function create(KycRequest $request)
    {
        try {
            $kyc = $this->kycService->create($request->validated());
            UserActivityHelper::LoggedInUserActivity('User created a kyc');
            return ResponseHelper::success($kyc, 'Kyc created successfully', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getKycForUser()
    {
        try {
            $user = Auth::user();
            $kyc = $this->kycService->getKycForUser($user->id);
            return ResponseHelper::success($kyc, 'Kyc fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getAll()
    {
        try {
            $kyc = $this->kycService->all();
            return ResponseHelper::success($kyc, 'Kyc fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function updateStatus(Request $request, $id)
    {
        $status = $request->status;
        $kyc = Kyc::find($id);
        if (!$kyc) {
            return ResponseHelper::error('Kyc not found', 404);
        }
        $kyc->status = $status;
        //check if status is rejected than also add rejection reason
        if ($status == 'rejected') {
            $kyc->rejection_reason = $request->rejection_reason;
        }
        $kyc->save();
        $userId = $kyc->user_id;
        $user = User::find($userId);
        $user->kyc_status = $status;
        $user->save();
        return ResponseHelper::success($kyc, 'Kyc updated successfully', 200);
    }
}
