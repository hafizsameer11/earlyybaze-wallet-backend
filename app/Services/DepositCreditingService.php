<?php

namespace App\Services;

use App\Helpers\ExchangeFeeHelper;
use App\Models\DepositAddress;
use App\Models\ReceivedAsset;
use App\Models\ReceiveTransaction;
use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
use App\Repositories\transactionRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepositCreditingService
{
    public function __construct(
        private transactionRepository $transactionRepository,
        private NotificationService $notificationService,
    ) {}

    public function creditVerifiedDeposit(
        ReceivedAsset $asset,
        array $webhookPayload,
        VirtualAccount $account,
        DepositAddress $deposit,
        string $reference,
        ?string $from = null,
    ): void {
        if ($asset->status !== 'processing') {
            return;
        }

        $amount = (string) $asset->amount;
        $currency = strtoupper((string) $asset->currency);
        $txId = (string) $asset->tx_id;
        $toAddr = $asset->to_address ?? $deposit->address;
        $from = $from ?? $asset->from_address ?? 'not provided';
        $txDate = $this->transactionCarbon($webhookPayload);

        DB::transaction(function () use (
            $asset, $webhookPayload, $account, $amount, $currency, $reference, $txId, $toAddr, $from, $txDate
        ) {
            $lockedAsset = ReceivedAsset::where('id', $asset->id)->lockForUpdate()->first();
            if (! $lockedAsset || $lockedAsset->status !== 'processing') {
                return;
            }

            if (WebhookResponse::where('reference', $reference)->exists()) {
                return;
            }

            $lockedAccount = VirtualAccount::where('id', $account->id)->lockForUpdate()->first();
            if (! $lockedAccount) {
                throw new \RuntimeException('Virtual account missing');
            }

            $currentBalance = (string) $lockedAccount->available_balance;
            $newBalance = bcadd($currentBalance, $amount, 8);
            $lockedAccount->available_balance = $newBalance;
            $lockedAccount->save();

            $exchangeRate = ExchangeFeeHelper::caclulateExchangeRate($amount, $currency);
            $amountUsd = $exchangeRate['amount_usd'];

            WebhookResponse::create([
                'account_id' => $lockedAccount->account_id,
                'subscription_type' => $webhookPayload['subscriptionType'] ?? null,
                'amount' => $amount,
                'reference' => $reference,
                'currency' => $currency,
                'tx_id' => $txId,
                'block_height' => $webhookPayload['blockNumber'] ?? null,
                'block_hash' => $webhookPayload['blockHash'] ?? null,
                'from_address' => $from,
                'to_address' => $toAddr,
                'transaction_date' => $txDate,
                'index' => $webhookPayload['logIndex'] ?? null,
            ]);

            $lockedAsset->status = 'inWallet';
            $lockedAsset->verification_status = 'verified';
            $lockedAsset->verification_error = null;
            $lockedAsset->verified_at = now();
            $lockedAsset->save();

            $this->notificationService->notifyUser(
                (int) $lockedAccount->user_id,
                'Deposit received',
                "You received {$amount} {$currency}. It has been credited to your wallet.",
                'deposit'
            );

            $transaction = $this->transactionRepository->create(data: [
                'type' => 'receive',
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'network' => $lockedAccount->blockchain,
                'reference' => $reference,
                'user_id' => $lockedAccount->user_id,
                'amount_usd' => $amountUsd,
                'transfer_type' => 'external',
            ]);

            ReceiveTransaction::create([
                'user_id' => $lockedAccount->user_id,
                'virtual_account_id' => $lockedAccount->id,
                'transaction_id' => $transaction->id,
                'transaction_type' => 'on_chain',
                'sender_address' => $from,
                'reference' => $reference,
                'tx_id' => $txId,
                'amount' => $amount,
                'currency' => $currency,
                'blockchain' => $lockedAccount->blockchain,
                'amount_usd' => $amountUsd,
                'status' => 'completed',
            ]);
        });
    }

    private function transactionCarbon(array $data): Carbon
    {
        $ms = $data['txTimestamp'] ?? $data['blockTimestamp'] ?? $data['timestamp'] ?? null;
        if ($ms !== null && is_numeric($ms)) {
            return Carbon::createFromTimestampMs((int) $ms);
        }

        return now();
    }
}
