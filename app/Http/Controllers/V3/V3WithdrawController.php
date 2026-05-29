<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Helpers\UserActivityHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V3\V3WithdrawRequest;
use App\Services\V3\V3WithdrawRequestService;
use Exception;
use Illuminate\Http\Request;

class V3WithdrawController extends Controller
{
    public function __construct(private V3WithdrawRequestService $service) {}

    public function create(V3WithdrawRequest $request)
    {
        try {
            $withdraw = $this->service->create($request->all());
            UserActivityHelper::LoggedInUserActivity('User created a ZAR withdraw request');

            return ResponseHelper::success($withdraw, 'Withdraw created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        try {
            $withdraw = $this->service->updateStatus($id, $request->all());

            return ResponseHelper::success($withdraw, 'Withdraw updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
