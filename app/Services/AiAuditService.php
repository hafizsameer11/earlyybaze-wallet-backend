<?php

namespace App\Services;

use App\Models\AiAuditReport;
use App\Models\FailedMasterTransfer;
use App\Models\RejectedDepositWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAuditService
{
    public function isOpenAiConfigured(): bool
    {
        return (string) env('OPENAI_API_KEY', '') !== '';
    }

    public function collectFailureSummary(int $limit = 30): array
    {
        $rejected = RejectedDepositWebhook::query()->latest('id')->limit($limit)->get([
            'id', 'rejection_reason', 'tx_id', 'chain', 'payload_currency', 'amount', 'created_at',
        ])->toArray();
        $failedTransfers = FailedMasterTransfer::query()->latest('id')->limit($limit)->get([
            'id', 'virtual_account_id', 'webhook_response_id', 'reason', 'created_at',
        ])->toArray();

        return [
            'rejected_deposits_count' => count($rejected),
            'failed_master_transfers_count' => count($failedTransfers),
            'rejected_deposits' => $rejected,
            'failed_master_transfers' => $failedTransfers,
        ];
    }

    public function runDailyAudit(string $triggeredBy = 'cron'): array
    {
        $summary = $this->collectFailureSummary();

        $apiKey = (string) env('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            $report = $this->storeReport([
                'success' => false,
                'message' => 'OPENAI_API_KEY is not configured.',
                'summary' => $summary,
                'analysis' => null,
                'triggered_by' => $triggeredBy,
            ]);

            return [
                'success' => false,
                'message' => 'OPENAI_API_KEY is not configured.',
                'data' => $summary,
                'report' => $report,
            ];
        }

        $model = (string) env('OPENAI_MODEL', 'gpt-4o-mini');
        $prompt = 'You are an audit assistant. Review these blockchain failures and produce: '.
            '1) anomalies, 2) duplicate/fraud suspicions, 3) operational actions. JSON only.'."\n\n".
            json_encode($summary);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return concise JSON with keys: anomalies, risks, actions.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            Log::error('AI audit request failed', ['status' => $response->status(), 'body' => $response->body()]);
            $report = $this->storeReport([
                'success' => false,
                'message' => 'AI audit request failed.',
                'summary' => $summary,
                'analysis' => $response->body(),
                'triggered_by' => $triggeredBy,
            ]);

            return [
                'success' => false,
                'message' => 'AI audit request failed.',
                'data' => $summary,
                'report' => $report,
            ];
        }

        $analysis = (string) $response->json('choices.0.message.content');
        $report = $this->storeReport([
            'success' => true,
            'message' => 'AI audit completed.',
            'summary' => $summary,
            'analysis' => $analysis,
            'triggered_by' => $triggeredBy,
        ]);

        return [
            'success' => true,
            'message' => 'AI audit completed.',
            'data' => $summary,
            'analysis' => $analysis,
            'report' => $report,
        ];
    }

    private function storeReport(array $payload): AiAuditReport
    {
        return AiAuditReport::create($payload);
    }
}
