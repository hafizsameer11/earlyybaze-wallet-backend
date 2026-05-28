<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AiAuditReport;
use App\Services\AiAuditService;
use Illuminate\Http\Request;

class AiAuditController extends Controller
{
    public function index(AiAuditService $service)
    {
        $latest = AiAuditReport::query()->latest('id')->first();
        $history = AiAuditReport::query()->latest('id')->limit(20)->get([
            'id', 'success', 'message', 'analysis', 'triggered_by', 'created_at',
        ]);

        return ResponseHelper::success([
            'openai_configured' => $service->isOpenAiConfigured(),
            'openai_model' => (string) env('OPENAI_MODEL', 'gpt-4o-mini'),
            'summary' => $service->collectFailureSummary(),
            'latest_report' => $latest,
            'history' => $history,
        ], 'AI audit dashboard fetched', 200);
    }

    public function run(Request $request, AiAuditService $service)
    {
        $result = $service->runDailyAudit('admin');

        return ResponseHelper::success([
            'report' => $result['report'] ?? null,
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'Done',
        ], $result['message'] ?? 'AI audit completed', ($result['success'] ?? false) ? 200 : 422);
    }
}
