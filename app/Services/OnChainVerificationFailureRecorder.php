<?php

namespace App\Services;

use App\DTO\OnChainVerificationResult;
use App\Models\OnChainVerificationFailure;
use App\Models\ReceivedAsset;
use Illuminate\Support\Facades\Log;

class OnChainVerificationFailureRecorder
{
    public static function recordDepositFailure(
        ReceivedAsset $asset,
        array $webhookPayload,
        OnChainVerificationResult $result,
        ?string $reference = null,
    ): void {
        try {
            OnChainVerificationFailure::create([
                'type' => OnChainVerificationFailure::TYPE_DEPOSIT,
                'received_asset_id' => $asset->id,
                'tx_id' => $asset->tx_id,
                'currency' => $asset->currency,
                'chain' => $webhookPayload['chain'] ?? null,
                'expected_from' => $asset->from_address,
                'expected_to' => $asset->to_address,
                'expected_amount' => (string) $asset->amount,
                'failure_code' => $result->failureCode ?? 'unknown',
                'failure_message' => $result->failureMessage,
                'tatum_response' => $result->raw ?: $result->toArray(),
                'webhook_payload' => $webhookPayload,
                'reference' => $reference ?? $asset->reference,
                'user_id' => $asset->user_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OnChainVerificationFailureRecorder deposit failed', [
                'received_asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function recordFlushFailure(
        ReceivedAsset $asset,
        OnChainVerificationResult $result,
        ?string $expectedFrom,
        string $expectedTo,
        string $expectedAmount,
    ): void {
        try {
            OnChainVerificationFailure::create([
                'type' => OnChainVerificationFailure::TYPE_FLUSH,
                'received_asset_id' => $asset->id,
                'tx_id' => $asset->transfered_tx ?? $asset->tx_id,
                'currency' => $asset->currency,
                'expected_from' => $expectedFrom,
                'expected_to' => $expectedTo,
                'expected_amount' => $expectedAmount,
                'failure_code' => $result->failureCode ?? 'unknown',
                'failure_message' => $result->failureMessage,
                'tatum_response' => $result->raw ?: $result->toArray(),
                'reference' => $asset->reference,
                'user_id' => $asset->user_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OnChainVerificationFailureRecorder flush failed', [
                'received_asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
