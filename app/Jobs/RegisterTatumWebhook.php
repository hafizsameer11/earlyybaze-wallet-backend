<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterTatumWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $virtualAccountId;
    public function __construct($virtualAccountId)
    {
        $this->virtualAccountId = $virtualAccountId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiKey = config('tatum.api_key');
        $baseUrl = config('tatum.base_url');
        $webhookUrl = config('tatum.webhook_url', 'https://yourdomain.com/api/webhooks/tatum');

        $payload = [
            'type' => 'ACCOUNT_INCOMING_BLOCKCHAIN_TRANSACTION',
            'attr' => [
                'id' => $this->virtualAccountId,
                'url' => $webhookUrl,
            ]
        ];

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post("$baseUrl/v3/subscription", $payload);

        if ($response->successful()) {
            Log::info("✅ Tatum webhook subscription registered successfully", [
                'virtualAccountId' => $this->virtualAccountId,
                'response' => $response->json(),
            ]);
        } else {
            Log::error("❌ Failed to register Tatum webhook subscription", [
                'virtualAccountId' => $this->virtualAccountId,
                'response' => $response->body(),
            ]);
        }
    }
}
