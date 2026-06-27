<?php

namespace App\Jobs;

use App\DTO\OnChainVerificationResult;
use App\Models\ReceivedAsset;
use App\Services\FlushBatchExpectations;
use App\Services\FlushCompletionService;
use App\Services\OnChainVerificationFailureRecorder;
use App\Services\TatumOnChainTxVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyFlushTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Allow time for BTC/TRON/USDT confirmations (up to ~2 hours with backoff). */
    public int $tries = 18;

    /** @var list<int> Seconds between retries for unconfirmed / not-yet-indexed txs. */
    public array $backoff = [60, 120, 180, 300, 300, 600, 600, 900, 900, 1200, 1200, 1800, 1800, 1800, 1800, 1800, 1800, 1800];

    /**
     * @param  list<int>  $receivedAssetIds
     */
    public function __construct(
        public array $receivedAssetIds,
        public string $currency,
        public string $txId,
        public ?string $expectedFrom,
        public string $expectedTo,
        public string $expectedAmount,
        public ?float $gasCost = null,
        public ?array $tatumResponseBody = null,
    ) {}

    public function handle(
        TatumOnChainTxVerifier $verifier,
        FlushCompletionService $completionService,
        FlushBatchExpectations $batchExpectations,
    ): void {
        $batch = $batchExpectations->resolveFromTx($this->txId, $this->currency);
        $expectedFrom = $batch['expected_from'] ?? $this->expectedFrom;
        $expectedTo = $batch['expected_to'] !== '' ? $batch['expected_to'] : $this->expectedTo;
        $expectedAmount = $batch['expected_amount'] ?? $this->expectedAmount;
        $assetIds = ($batch['pending_asset_ids'] ?? []) !== []
            ? $batch['pending_asset_ids']
            : $this->receivedAssetIds;

        $result = $verifier->verifyFlush(
            $this->currency,
            $this->txId,
            $expectedFrom,
            $expectedTo,
            $expectedAmount,
        );

        if ($result->isSuccess()) {
            $completionService->completeVerifiedFlush(
                $assetIds,
                $this->currency,
                $this->txId,
                $expectedFrom,
                $expectedTo,
                $expectedAmount,
                $this->gasCost,
                $result,
            );

            return;
        }

        if ($result->failureCode === OnChainVerificationResult::FAIL_TX_UNCONFIRMED) {
            $this->markConfirming($result);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 1800);

                return;
            }

            Log::warning('Flush tx still unconfirmed after max job attempts; cron will continue checking', [
                'tx_id' => $this->txId,
                'currency' => $this->currency,
                'attempts' => $this->attempts(),
            ]);

            return;
        }

        if ($result->failureCode === OnChainVerificationResult::FAIL_TX_NOT_FOUND
            && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 180);

            return;
        }

        $this->markFlushFailed($result);
    }

    private function markConfirming(OnChainVerificationResult $result): void
    {
        ReceivedAsset::whereIn('id', $this->receivedAssetIds)
            ->where('transfered_tx', $this->txId)
            ->where('status', '!=', 'completed')
            ->update([
                'flush_status' => 'confirming',
                'verification_error' => $result->failureMessage,
            ]);
    }

    private function markFlushFailed(OnChainVerificationResult $result): void
    {
        $batch = app(FlushBatchExpectations::class)->resolveFromTx($this->txId, $this->currency);
        $expectedFrom = $batch['expected_from'] ?? $this->expectedFrom;
        $expectedTo = $batch['expected_to'] !== '' ? $batch['expected_to'] : $this->expectedTo;
        $expectedAmount = $batch['expected_amount'] ?? $this->expectedAmount;
        $assetIds = ($batch['pending_asset_ids'] ?? []) !== []
            ? $batch['pending_asset_ids']
            : $this->receivedAssetIds;

        $items = ReceivedAsset::whereIn('id', $assetIds)->get();

        foreach ($items as $it) {
            if ($it->status === 'completed' && $it->flush_status === 'verified') {
                continue;
            }

            $it->flush_status = 'failed';
            $it->verification_error = $result->failureMessage ?? $result->failureCode;
            $it->transfered_tx = $this->txId;
            $it->save();

            OnChainVerificationFailureRecorder::recordFlushFailure(
                $it,
                $result,
                $expectedFrom,
                $expectedTo,
                $expectedAmount,
            );
        }

        Log::warning('Flush on-chain verification failed', [
            'tx_id' => $this->txId,
            'currency' => $this->currency,
            'failure' => $result->toArray(),
        ]);
    }
}
