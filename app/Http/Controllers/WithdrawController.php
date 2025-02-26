<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\WithdrawRequest;
use App\Services\WithdrawRequestService;
use Exception;
use Illuminate\Http\Request;

class WithdrawController extends Controller
{
    protected $withdrawService;
    public function __construct(WithdrawRequestService $withdrawService)
    {
        $this->withdrawService = $withdrawService;
    }
    public function create(WithdrawRequest $request)
    {
        try {
            $withdraw = $this->withdrawService->create($request->all());
            return ResponseHelper::success($withdraw, 'Withdraw created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getwithdrawRequestStatus($id)
    {
        try {
            $withdraw = $this->withdrawService->getwithdrawRequestStatus($id);
            return ResponseHelper::success($withdraw, 'Withdraw fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getWithDrawRequestforAuthenticatedUser()
    {
        try {
            $withdraw = $this->withdrawService->getWithDrawRequestforAuthenticatedUser();
            return ResponseHelper::success($withdraw, 'Withdraw fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
