<?php

namespace App\Jobs;

use App\Helpers\ExchangeFeeHelper;
use App\Models\DepositAddress;
use App\Models\MasterWallet;
use App\Models\ReceivedAsset;
use App\Models\ReceiveTransaction;
use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
use App\Support\AllowedFungibleContracts;
use App\Repositories\transactionRepository;
use App\Services\NotificationService;
use App\Services\RejectedDepositWebhookRecorder;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTatumV4DepositWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function handle(
        transactionRepository $transactionRepository,
        NotificationService $notificationService
    ): void {
        $data = $this->data;

        $txId = (string) ($data['txId'] ?? '');
        $refSuffix = $txId;
        if (array_key_exists('logIndex', $data)) {
            $refSuffix .= '-'.(string) $data['logIndex'];
        }
        $reference = 'v4-'.($data['subscriptionId'] ?? 'na').'-'.$refSuffix;

        $from = $this->senderAddress($data);
        if ($from) {
            $master = MasterWallet::whereRaw('LOWER(address) = ?', [strtolower($from)])->first();
            if ($master) {
                Log::info('v4 webhook ignored: sender is master wallet', ['from' => $from]);

                return;
            }
        }

        if ($txId !== '' && WebhookResponse::where('reference', $reference)->exists()) {
            Log::info('v4 webhook duplicate reference', ['reference' => $reference]);

            return;
        }

        if ($this->isDuplicateOnChainDeposit($txId, $data)) {
            Log::info('v4 webhook ignored: duplicate tx_id/logIndex already credited', [
                'txId' => $txId,
                'logIndex' => AllowedFungibleContracts::payloadLogIndex($data),
            ]);

            return;
        }

        if (AllowedFungibleContracts::isFungiblePayload($data)) {
            $contract = AllowedFungibleContracts::payloadContract($data);
            if (! AllowedFungibleContracts::isAllowed($contract)) {
                RejectedDepositWebhookRecorder::record(
                    'v2',
                    AllowedFungibleContracts::REJECT_NON_ALLOWLISTED_CONTRACT,
                    $data,
                    null,
                    $reference
                );
                Log::info('v4 webhook ignored: fungible tx with non-allowlisted contract', [
                    'txId' => $txId,
                    'contractAddress' => $contract,
                    'to' => $data['to'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'symbol' => $data['tokenMetadata']['symbol'] ?? null,
                ]);

                return;
            }
        }

        $deposit = $this->findDeposit($data);
        if (! $deposit) {
            Log::warning('v4 webhook: no matching v2 deposit', ['payload' => $data]);

            return;
        }

        $account = $deposit->virtualAccount;
        if (! $account) {
            Log::warning('v4 webhook: deposit missing virtual account');

            return;
        }

        if (! $this->isV2OnChainDeposit($deposit, $account)) {
            Log::warning('v4 webhook ignored: not v2 on-chain (ledger or v1 user)', [
                'deposit_id' => $deposit->id,
                'virtual_account_id' => $account->id,
            ]);

            return;
        }

        $fungibleReject = AllowedFungibleContracts::rejectReasonForFungibleDeposit($account, $data);
        if ($fungibleReject !== null) {
            RejectedDepositWebhookRecorder::record('v2', $fungibleReject, $data, $account, $reference);
            Log::info('v4 webhook ignored: '.AllowedFungibleContracts::rejectionReasonLabel($fungibleReject), [
                'txId' => $txId,
                'reason' => $fungibleReject,
                'account_currency' => $account->currency,
                'contractAddress' => AllowedFungibleContracts::payloadContract($data),
                'payload_currency' => $data['currency'] ?? null,
            ]);

            return;
        }

        $lockKey = 'webhook_lock_'.$reference;
        $lock = Cache::lock($lockKey, 120);
        if (! $lock->get()) {
            Log::warning('v4 webhook lock busy', ['reference' => $reference]);

            return;
        }

        try {
            $amount = $this->inboundAmount($data);
            $currency = strtoupper((string) $account->currency);
            $toAddr = $this->recipientAddress($data) ?? $deposit->address;

            DB::transaction(function () use (
                $data, $account, $amount, $currency, $reference, $txId, $toAddr, $from,
                $transactionRepository, $notificationService
            ) {
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

                $txDate = $this->transactionCarbon($data);

                WebhookResponse::create([
                    'account_id' => $lockedAccount->account_id,
                    'subscription_type' => $data['subscriptionType'] ?? null,
                    'amount' => $amount,
                    'reference' => $reference,
                    'currency' => $currency,
                    'tx_id' => $txId,
                    'block_height' => $data['blockNumber'] ?? null,
                    'block_hash' => $data['blockHash'] ?? null,
                    'from_address' => $from ?? 'not provided',
                    'to_address' => $toAddr,
                    'transaction_date' => $txDate,
                    'index' => $data['logIndex'] ?? null,
                ]);

                ReceivedAsset::create([
                    'account_id' => $lockedAccount->account_id,
                    'subscription_type' => $data['subscriptionType'] ?? null,
                    'amount' => $amount,
                    'reference' => $reference,
                    'currency' => $currency,
                    'tx_id' => $txId,
                    'from_address' => $from ?? 'not provided',
                    'to_address' => $toAddr,
                    'transaction_date' => $txDate,
                    'status' => 'inWallet',
                    'index' => $data['logIndex'] ?? null,
                    'user_id' => $lockedAccount->user_id,
                ]);

                $notificationService->sendToUserById(
                    $lockedAccount->user_id,
                    "You have received {$amount} {$currency}",
                    'Your amount is being processed'
                );

                $transaction = $transactionRepository->create(data: [
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
                    'sender_address' => $from ?? '',
                    'reference' => $reference,
                    'tx_id' => $txId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'blockchain' => $lockedAccount->blockchain,
                    'amount_usd' => $amountUsd,
                    'status' => 'completed',
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('ProcessTatumV4DepositWebhook failed', ['error' => $e->getMessage(), 'reference' => $reference]);
        } finally {
            optional($lock)->release();
        }
    }

    /** Tatum enriched: `to` + `value` + `from` + `txTimestamp` / `tokenMetadata`. */
    private function recipientAddress(array $data): ?string
    {
        $a = $data['to'] ?? $data['address'] ?? null;

        return $a !== null && $a !== '' ? (string) $a : null;
    }

    private function senderAddress(array $data): ?string
    {
        $a = $data['from'] ?? $data['counterAddress'] ?? null;
        if ($a === null && ! empty($data['counterAddresses'][0])) {
            $a = $data['counterAddresses'][0];
        }

        return $a !== null && $a !== '' ? (string) $a : null;
    }

    private function inboundAmount(array $data): string
    {
        $raw = $data['value'] ?? $data['amount'] ?? '0';

        return (string) $raw;
    }

    private function transactionCarbon(array $data): Carbon
    {
        $ms = $data['txTimestamp'] ?? $data['blockTimestamp'] ?? $data['timestamp'] ?? null;
        if ($ms !== null && is_numeric($ms)) {
            return Carbon::createFromTimestampMs((int) $ms);
        }

        return now();
    }

    /**
     * Map chain + tokenMetadata (+ subscriptionType/kind) to wallet_currencies.currency codes we use.
     *
     * @return list<string>
     */
    private function inferredWalletCurrencies(array $data): array
    {
        $chain = strtolower((string) ($data['chain'] ?? ''));
        $meta = is_array($data['tokenMetadata'] ?? null) ? $data['tokenMetadata'] : [];
        $symbol = strtoupper(trim((string) ($meta['symbol'] ?? '')));
        $metaType = strtolower(trim((string) ($meta['type'] ?? '')));
        $subType = strtoupper((string) ($data['subscriptionType'] ?? ''));
        $kind = strtolower((string) ($data['kind'] ?? ''));

        $isNativeEvent = $subType === 'INCOMING_NATIVE_TX'
            || ($metaType === 'native' && $kind === 'transfer');

        if ($isNativeEvent) {
            if (str_contains($chain, 'ethereum')) {
                return array_values(array_unique(array_filter(['ETH', $symbol])));
            }
            if (str_contains($chain, 'bsc')) {
                return array_values(array_unique(array_filter(['BNB', 'BSC', $symbol])));
            }
            if (str_contains($chain, 'tron')) {
                return ['TRON', 'TRX'];
            }
            if (str_contains($chain, 'bitcoin') || str_contains($chain, 'btc')) {
                return array_values(array_unique(array_filter(['BTC', $symbol])));
            }

            return $symbol !== '' ? [$symbol] : [];
        }

        $isFungibleEvent = $subType === 'INCOMING_FUNGIBLE_TX'
            || $metaType === 'fungible'
            || $kind === 'token_transfer';

        if ($isFungibleEvent) {
            if (str_contains($chain, 'tron')) {
                if ($symbol === 'USDT') {
                    return ['USDT_TRON'];
                }
                if ($symbol === 'USDC') {
                    return ['USDC_TRON'];
                }
            }
            if (str_contains($chain, 'bsc')) {
                if ($symbol === 'USDT') {
                    return ['USDT_BSC'];
                }
                if ($symbol === 'USDC') {
                    return ['USDC_BSC'];
                }
            }
            if (str_contains($chain, 'ethereum')) {
                if ($symbol === 'USDT') {
                    return ['USDT', 'USDT_ETH'];
                }
                if ($symbol === 'USDC') {
                    return ['USDC', 'USDC_ETH'];
                }
            }

            // Do not fall back to payload currency for fungible events (e.g. fake "ETH" tokens).
            return [];
        }

        $top = strtoupper(trim((string) ($data['currency'] ?? '')));

        return $top !== '' ? [$top] : [];
    }

    private function virtualAccountMatchesPayload(VirtualAccount $va, array $data, string $subType): bool
    {
        $vaCur = strtoupper((string) $va->currency);
        $expected = array_map('strtoupper', $this->inferredWalletCurrencies($data));

        if ($expected !== []) {
            return in_array($vaCur, $expected, true);
        }

        $top = strtoupper(trim((string) ($data['currency'] ?? '')));

        return $top !== '' && $vaCur === $top;
    }

    private function findDeposit(array $data): ?DepositAddress
    {
        $addr = $this->recipientAddress($data);
        if (! $addr) {
            return null;
        }

        $subType = (string) ($data['subscriptionType'] ?? '');

        $candidates = DepositAddress::query()
            ->where('version', 'v2')
            ->whereRaw('LOWER(address) = ?', [strtolower($addr)])
            ->with(['virtualAccount.walletCurrency', 'virtualAccount.user'])
            ->get();

        foreach ($candidates as $dep) {
            $va = $dep->virtualAccount;
            if (! $va) {
                continue;
            }
            $wc = $va->walletCurrency;
            $isToken = $wc ? (bool) ($wc->is_token ?? false) : false;

            if ($subType === 'INCOMING_FUNGIBLE_TX') {
                if ($this->fungibleDepositMatches($va, $data)) {
                    return $dep;
                }

                continue;
            }

            if ($subType === 'INCOMING_NATIVE_TX') {
                if ($isToken) {
                    continue;
                }
                if (! $this->virtualAccountMatchesPayload($va, $data, $subType)) {
                    continue;
                }

                return $dep;
            }

            if ($this->virtualAccountMatchesPayload($va, $data, $subType)) {
                return $dep;
            }
        }

        return null;
    }

    private function fungibleDepositMatches(VirtualAccount $va, array $data): bool
    {
        // Fungible deposits must match an allowlisted contract for this wallet currency.
        // Never fall back to payload currency/symbol (prevents fake tokens credited as ETH).
        return AllowedFungibleContracts::matchesVirtualAccount(
            $va,
            $data['contractAddress'] ?? null
        );
    }

    private function isV2OnChainDeposit(DepositAddress $deposit, VirtualAccount $account): bool
    {
        if (($deposit->version ?? '') !== 'v2') {
            return false;
        }

        return true;
    }

    private function addressesEqual(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_starts_with(strtolower($a), '0x') && str_starts_with(strtolower($b), '0x')) {
            return strtolower($a) === strtolower($b);
        }

        return strcasecmp($a, $b) === 0;
    }

    private function isDuplicateOnChainDeposit(string $txId, array $data): bool
    {
        if ($txId === '') {
            return false;
        }

        $q = ReceivedAsset::query()->where('tx_id', $txId);
        $logIndex = AllowedFungibleContracts::payloadLogIndex($data);
        if ($logIndex !== null) {
            $q->where('index', $logIndex);
        }

        return $q->exists();
    }
}
