<?php

namespace App\Services;

use App\Models\FailedMasterTransfer;
use App\Models\RejectedDepositWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAuditService
{
    public function runDailyAudit(): array
    {
        $rejected = RejectedDepositWebhook::query()->latest('id')->limit(30)->get([
            'id', 'rejection_reason', 'tx_id', 'chain', 'payload_currency', 'amount', 'created_at',
        ])->toArray();
        $failedTransfers = FailedMasterTransfer::query()->latest('id')->limit(30)->get([
            'id', 'virtual_account_id', 'webhook_response_id', 'reason', 'created_at',
        ])->toArray();

        $summary = [
            'rejected_deposits_count' => count($rejected),
            'failed_master_transfers_count' => count($failedTransfers),
            'rejected_deposits' => $rejected,
            'failed_master_transfers' => $failedTransfers,
        ];

        $apiKey = (string) env('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => 'OPENAI_API_KEY is not configured.',
                'data' => $summary,
            ];
        }

        $model = (string) env('OPENAI_MODEL', 'gpt-4o-mini');
        $prompt = "You are an audit assistant. Review these blockchain failures and produce: ".
            "1) anomalies, 2) duplicate/fraud suspicions, 3) operational actions. JSON only.\n\n".
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
            return [
                'success' => false,
                'message' => 'AI audit request failed.',
                'data' => $summary,
            ];
        }

        return [
            'success' => true,
            'message' => 'AI audit completed.',
            'data' => $summary,
            'analysis' => $response->json('choices.0.message.content'),
        ];
    }
}
