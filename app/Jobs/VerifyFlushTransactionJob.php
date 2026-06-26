<?php

namespace App\Jobs;

use App\DTO\OnChainVerificationResult;
use App\Models\ReceivedAsset;
use App\Models\TransferLog;
use App\Services\OnChainVerificationFailureRecorder;
use App\Services\TatumOnChainTxVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyFlushTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

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

    public function handle(TatumOnChainTxVerifier $verifier): void
    {
        $result = $verifier->verifyFlush(
            $this->currency,
            $this->txId,
            $this->expectedFrom,
            $this->expectedTo,
            $this->expectedAmount,
        );

        if ($result->isSuccess()) {
            $this->markCompleted($result);

            return;
        }

        if ($result->failureCode === OnChainVerificationResult::FAIL_TX_NOT_FOUND
            && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 120);

            return;
        }

        $this->markFlushFailed($result);
    }

    private function markCompleted(OnChainVerificationResult $result): void
    {
        DB::transaction(function () use ($result) {
            $items = ReceivedAsset::whereIn('id', $this->receivedAssetIds)
                ->where('flush_status', 'pending')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return;
            }

            TransferLog::create([
                'from_address' => $this->expectedFrom ?? 'BATCH',
                'to_address' => $this->expectedTo,
                'amount' => (float) $this->expectedAmount,
                'currency' => $this->currency,
                'tx' => json_encode($result->raw ?: ['txId' => $this->txId]),
            ]);

            foreach ($items as $it) {
                $it->status = 'completed';
                $it->flush_status = 'verified';
                $it->transfered_tx = $this->txId;
                $it->transfer_address = $this->expectedFrom ?? $it->transfer_address;
                $it->address_to_send = $this->expectedTo;
                $it->transfered_amount = (float) $it->amount;
                $it->gas_fee = $this->gasCost;
                $it->verified_at = now();
                $it->verification_error = null;
                $it->save();
            }
        });
    }

    private function markFlushFailed(OnChainVerificationResult $result): void
    {
        $items = ReceivedAsset::whereIn('id', $this->receivedAssetIds)->get();

        foreach ($items as $it) {
            $it->flush_status = 'failed';
            $it->verification_error = $result->failureMessage ?? $result->failureCode;
            $it->transfered_tx = $this->txId;
            $it->save();

            OnChainVerificationFailureRecorder::recordFlushFailure(
                $it,
                $result,
                $this->expectedFrom,
                $this->expectedTo,
                $this->expectedAmount,
            );
        }

        Log::warning('Flush on-chain verification failed', [
            'tx_id' => $this->txId,
            'currency' => $this->currency,
            'failure' => $result->toArray(),
        ]);
    }
}
