<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\V3\V3ReferralLeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class V3ReferralLeaderboardController extends Controller
{
    public function __construct(
        private V3ReferralLeaderboardService $service,
    ) {}

    /**
     * GET /api/v3/referral/leaderboard?period=weekly|monthly|all_time&limit=50
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:weekly,monthly,all_time,week,month,all,alltime,all-time',
            'limit' => 'nullable|integer|min:10|max:100',
        ]);

        try {
            $data = $this->service->getLeaderboard(
                (int) Auth::id(),
                (string) ($validated['period'] ?? 'weekly'),
                (int) ($validated['limit'] ?? 50),
            );

            return ResponseHelper::success($data, 'Leaderboard fetched successfully', 200);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
