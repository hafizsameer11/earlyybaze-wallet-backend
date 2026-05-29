<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V3\V3SwapTransactionRequest;
use App\Models\UserActivity;
use App\Services\V3\V3SwapTransactionService;
use Illuminate\Support\Facades\Auth;

class V3SwapController extends Controller
{
    public function __construct(private V3SwapTransactionService $service) {}

    public function swap(V3SwapTransactionRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->service->swap($request->validated());
            $data = $request->validated();

            UserActivity::create([
                'user_id' => $user->id,
                'content' => "You have successfully swapped {$data['amount']}{$data['currency']} to ZAR",
            ]);

            return ResponseHelper::success($transaction, 'Swap created successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function completeSwapTransaction($id)
    {
        try {
            $swap = $this->service->completeSwapTransaction($id);

            return ResponseHelper::success($swap, 'Swap completed successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function singleSwapTransaction($id)
    {
        try {
            $swap = $this->service->singleSwapTransaction($id);

            return ResponseHelper::success($swap, 'Swap fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
