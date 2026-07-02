<?php

namespace App\Services;

use App\DTO\OnChainVerificationResult;
use App\Models\OnChainVerificationFailure;
use App\Models\ReceivedAsset;
use App\Models\TransferLog;
use Illuminate\Support\Facades\DB;

class FlushCompletionService
{
    /**
     * Mark flushed assets completed after on-chain verification succeeds.
     *
     * @param  list<int>  $receivedAssetIds
     */
    public function completeVerifiedFlush(
        array $receivedAssetIds,
        string $currency,
        string $txId,
        ?string $expectedFrom,
        string $expectedTo,
        string $expectedAmount,
        ?float $gasCost,
        OnChainVerificationResult $result,
    ): int {
        $completed = 0;

        DB::transaction(function () use (
            $receivedAssetIds,
            $currency,
            $txId,
            $expectedFrom,
            $expectedTo,
            $expectedAmount,
            $gasCost,
            $result,
            &$completed,
        ) {
            $items = ReceivedAsset::whereIn('id', $receivedAssetIds)
                ->whereNotNull('transfered_tx')
                ->where('transfered_tx', $txId)
                ->whereIn('flush_status', ['pending', 'confirming', 'failed'])
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return;
            }

            TransferLog::create([
                'from_address' => $expectedFrom ?? 'BATCH',
                'to_address' => $expectedTo,
                'amount' => (float) $expectedAmount,
                'currency' => $currency,
                'tx' => json_encode($result->raw ?: ['txId' => $txId]),
            ]);

            foreach ($items as $it) {
                $it->status = 'completed';
                $it->flush_status = 'verified';
                $it->transfered_tx = $txId;
                $it->transfer_address = $expectedFrom ?? $it->transfer_address;
                $it->address_to_send = $expectedTo;
                $it->transfered_amount = (float) $it->amount;
                $it->gas_fee = $gasCost;
                $it->verified_at = now();
                $it->verification_error = null;
                $it->save();
                $completed++;

                OnChainVerificationFailure::query()
                    ->where('received_asset_id', $it->id)
                    ->where('type', OnChainVerificationFailure::TYPE_FLUSH)
                    ->whereNull('resolved_at')
                    ->update([
                        'resolved_at' => now(),
                        'resolution' => OnChainVerificationFailure::RESOLUTION_APPROVED,
                        'failure_message' => 'Auto-resolved: flush confirmed on chain',
                    ]);
            }
        });

        return $completed;
    }
}
