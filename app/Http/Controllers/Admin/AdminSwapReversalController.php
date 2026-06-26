<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSwapReversalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSwapReversalController extends Controller
{
    public function preview(int $swapId, AdminSwapReversalService $service)
    {
        try {
            return ResponseHelper::success(
                $service->preview($swapId),
                'Swap reversal preview generated',
                200,
            );
        } catch (Exception $e) {
            Log::warning('Swap reversal preview failed', ['swap_id' => $swapId, 'error' => $e->getMessage()]);

            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function execute(Request $request, int $swapId, AdminSwapReversalService $service)
    {
        try {
            $result = $service->execute(
                $swapId,
                auth()->id(),
                $request->input('admin_note'),
            );

            return ResponseHelper::success($result, $result['message'] ?? 'Swap reversed', 200);
        } catch (Exception $e) {
            Log::error('Swap reversal execute failed', ['swap_id' => $swapId, 'error' => $e->getMessage()]);

            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
