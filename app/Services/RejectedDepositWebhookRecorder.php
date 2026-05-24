<?php

namespace App\Services;

use App\Models\RejectedDepositWebhook;
use App\Models\VirtualAccount;
use App\Support\AllowedFungibleContracts;
use Illuminate\Support\Facades\Log;

class RejectedDepositWebhookRecorder
{
    /**
     * Persist a rejected fungible / fake-token webhook for admin review.
     * Never throws — logging failure must not break webhook handling.
     */
    public static function record(
        string $channel,
        string $rejectionReason,
        array $data,
        ?VirtualAccount $account = null,
        ?string $reference = null
    ): void {
        try {
            $meta = is_array($data['tokenMetadata'] ?? null) ? $data['tokenMetadata'] : [];
            $amount = (string) ($data['value'] ?? $data['amount'] ?? '0');

            RejectedDepositWebhook::create([
                'channel' => $channel,
                'rejection_reason' => $rejectionReason,
                'subscription_type' => $data['subscriptionType'] ?? null,
                'tx_id' => isset($data['txId']) ? (string) $data['txId'] : null,
                'log_index' => AllowedFungibleContracts::payloadLogIndex($data),
                'contract_address' => AllowedFungibleContracts::payloadContract($data) ?: null,
                'payload_currency' => isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
                'account_currency' => $account ? strtoupper((string) $account->currency) : null,
                'amount' => $amount !== '' ? $amount : null,
                'chain' => $data['chain'] ?? null,
                'from_address' => $data['from'] ?? null,
                'to_address' => $data['to'] ?? $data['address'] ?? null,
                'account_id' => $account?->account_id ?? ($data['accountId'] ?? null),
                'user_id' => $account?->user_id,
                'token_symbol' => isset($meta['symbol']) ? (string) $meta['symbol'] : null,
                'token_name' => isset($meta['name']) ? (string) $meta['name'] : null,
                'reference' => $reference,
                'payload' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('RejectedDepositWebhookRecorder failed', [
                'channel' => $channel,
                'reason' => $rejectionReason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
