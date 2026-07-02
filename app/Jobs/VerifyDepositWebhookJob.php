<?php

namespace App\Jobs;

use App\DTO\OnChainVerificationResult;
use App\Models\DepositAddress;
use App\Models\ReceivedAsset;
use App\Models\VirtualAccount;
use App\Services\DepositCreditingService;
use App\Services\OnChainVerificationFailureRecorder;
use App\Services\RejectedDepositWebhookRecorder;
use App\Services\TatumOnChainTxVerifier;
use App\Support\AllowedFungibleContracts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyDepositWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $receivedAssetId,
        public array $webhookPayload,
        public string $reference,
        public ?int $depositAddressId,
        public int $virtualAccountId,
    ) {}

    public function handle(
        TatumOnChainTxVerifier $verifier,
        DepositCreditingService $creditingService,
    ): void {
        $asset = ReceivedAsset::find($this->receivedAssetId);
        if (! $asset || $asset->status !== 'processing') {
            return;
        }

        $account = VirtualAccount::find($this->virtualAccountId);
        $deposit = $this->depositAddressId
            ? DepositAddress::find($this->depositAddressId)
            : null;

        if (! $deposit && ! empty($this->webhookPayload['to'])) {
            $deposit = DepositAddress::query()
                ->where('virtual_account_id', $this->virtualAccountId)
                ->whereRaw('LOWER(address) = ?', [strtolower((string) $this->webhookPayload['to'])])
                ->first();
        }

        if (! $account) {
            Log::warning('VerifyDepositWebhookJob missing account or deposit', [
                'received_asset_id' => $this->receivedAssetId,
            ]);

            return;
        }

        if (! $deposit) {
            $deposit = new DepositAddress([
                'address' => $this->webhookPayload['to'] ?? $asset->to_address,
                'virtual_account_id' => $account->id,
            ]);
        }

        if (! config('tatum.deposit_on_chain_verify', true)) {
            Log::info('VerifyDepositWebhookJob: on-chain verification disabled, crediting from webhook', [
                'received_asset_id' => $this->receivedAssetId,
                'reference' => $this->reference,
            ]);
            $creditingService->creditVerifiedDeposit(
                $asset,
                $this->webhookPayload,
                $account,
                $deposit,
                $this->reference,
            );

            return;
        }

        $result = $verifier->verifyDeposit($this->webhookPayload, $account, $deposit);

        if ($result->isSuccess()) {
            $creditingService->creditVerifiedDeposit(
                $asset,
                $this->webhookPayload,
                $account,
                $deposit,
                $this->reference,
            );

            return;
        }

        if ($result->failureCode === OnChainVerificationResult::FAIL_TX_NOT_FOUND
            && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 120);

            return;
        }

        $this->markFailed($asset, $result);
    }

    public function failed(\Throwable $exception): void
    {
        $asset = ReceivedAsset::find($this->receivedAssetId);
        if ($asset && $asset->status === 'processing') {
            $this->markFailed($asset, OnChainVerificationResult::notFound($exception->getMessage()));
        }
    }

    private function markFailed(ReceivedAsset $asset, OnChainVerificationResult $result): void
    {
        $reason = match ($result->failureCode) {
            OnChainVerificationResult::FAIL_TX_FAILED => AllowedFungibleContracts::REJECT_ON_CHAIN_TX_FAILED,
            OnChainVerificationResult::FAIL_ADDRESS_MISMATCH => AllowedFungibleContracts::REJECT_ON_CHAIN_ADDRESS_MISMATCH,
            OnChainVerificationResult::FAIL_AMOUNT_MISMATCH => AllowedFungibleContracts::REJECT_ON_CHAIN_AMOUNT_MISMATCH,
            OnChainVerificationResult::FAIL_CONTRACT_MISMATCH => AllowedFungibleContracts::REJECT_ON_CHAIN_CONTRACT_MISMATCH,
            default => AllowedFungibleContracts::REJECT_ON_CHAIN_TX_NOT_FOUND,
        };

        $asset->status = 'failed';
        $asset->verification_status = 'failed';
        $asset->verification_error = $result->failureMessage ?? $result->failureCode;
        $asset->save();

        RejectedDepositWebhookRecorder::record(
            'v2',
            $reason,
            array_merge($this->webhookPayload, ['verification' => $result->toArray()]),
            VirtualAccount::find($this->virtualAccountId),
            $this->reference,
        );

        OnChainVerificationFailureRecorder::recordDepositFailure(
            $asset,
            $this->webhookPayload,
            $result,
            $this->reference,
        );
    }
}
