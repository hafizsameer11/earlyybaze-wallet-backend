<?php

namespace App\Jobs;

use App\Helpers\ExchangeFeeHelper;
use App\Models\DepositAddress;
use App\Support\WalletFlowV2;
use App\Models\MasterWallet;
use App\Models\ReceivedAsset;
use App\Models\ReceiveTransaction;
use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
use App\Repositories\transactionRepository;
use App\Services\NotificationService;
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

    private const TRON_MAINNET_USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private const TRON_MAINNET_USDC_CONTRACT = 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8';

    private const ETH_MAINNET_USDT_CONTRACT = '0xdac17f958d2ee523a2206206994597c13d831ec7';

    private const ETH_MAINNET_USDC_CONTRACT = '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48';

    private const BSC_MAINNET_USDT_CONTRACT = '0x55d398326f99059ff775485246999027b3197955';

    private const BSC_MAINNET_USDC_CONTRACT = '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d';

    public function __construct(
        public array $data
    ) {}

    public function handle(
        transactionRepository $transactionRepository,
        NotificationService $notificationService
    ): void {
        $data = $this->data;
        $reference = 'v4-'.($data['subscriptionId'] ?? 'na').'-'.($data['txId'] ?? uniqid('', true));

        $from = $data['counterAddress'] ?? ($data['counterAddresses'][0] ?? null);
        if ($from) {
            $master = MasterWallet::whereRaw('LOWER(address) = ?', [strtolower($from)])->first();
            if ($master) {
                Log::info('v4 webhook ignored: sender is master wallet', ['from' => $from]);

                return;
            }
        }

        if (isset($data['txId']) && WebhookResponse::where('reference', $reference)->exists()) {
            Log::info('v4 webhook duplicate reference', ['reference' => $reference]);

            return;
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

        $lockKey = 'webhook_lock_'.$reference;
        $lock = Cache::lock($lockKey, 120);
        if (! $lock->get()) {
            Log::warning('v4 webhook lock busy', ['reference' => $reference]);

            return;
        }

        try {
            $amount = (string) ($data['amount'] ?? '0');
            $currency = strtoupper((string) $account->currency);
            $txId = (string) ($data['txId'] ?? '');
            $toAddr = (string) ($data['address'] ?? $deposit->address);

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

                $txDate = isset($data['timestamp'])
                    ? Carbon::createFromTimestampMs((int) $data['timestamp'])
                    : now();

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
                    'index' => null,
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
                    'index' => null,
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

    private function findDeposit(array $data): ?DepositAddress
    {
        $addr = $data['address'] ?? null;
        if (! $addr) {
            return null;
        }

        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $subType = (string) ($data['subscriptionType'] ?? '');

        $candidates = DepositAddress::query()
            ->where('version', 'v2')
            ->whereRaw('LOWER(address) = ?', [strtolower($addr)])
            ->whereHas('virtualAccount', function ($q): void {
                $q->where('is_tatum_ledger', false);
            })
            ->whereHas('virtualAccount.user', function ($q): void {
                $q->where('wallet_flow_version', 'v2');
            })
            ->with(['virtualAccount.walletCurrency', 'virtualAccount.user'])
            ->get();

        foreach ($candidates as $dep) {
            $va = $dep->virtualAccount;
            if (! $va) {
                continue;
            }
            $wc = $va->walletCurrency;
            if (! $wc || ! WalletFlowV2::currencyAllowedForV2($wc)) {
                continue;
            }

            if ($subType === 'INCOMING_FUNGIBLE_TX') {
                if ($this->fungibleContractMatches($va, $data['contractAddress'] ?? null)) {
                    return $dep;
                }

                continue;
            }

            if ($subType === 'INCOMING_NATIVE_TX') {
                if (strtoupper((string) $va->currency) !== $currency) {
                    continue;
                }
                if ($wc && ($wc->is_token ?? false)) {
                    continue;
                }

                return $dep;
            }

            if (strtoupper((string) $va->currency) !== $currency) {
                continue;
            }

            return $dep;
        }

        return null;
    }

    private function fungibleContractMatches(VirtualAccount $va, mixed $payloadContract): bool
    {
        $wc = $va->walletCurrency;
        if (! $wc) {
            return false;
        }

        $their = trim((string) $payloadContract);
        $theirUpper = strtoupper($their);
        $ours = trim((string) ($wc->contract_address ?? ''));
        $vaCur = strtoupper((string) $va->currency);

        if ($ours !== '' && $their !== '' && $this->addressesEqual($ours, $their)) {
            return true;
        }

        if ($theirUpper === 'USDT_TRON' && $vaCur === 'USDT_TRON') {
            return true;
        }
        if ($theirUpper === 'USDC_TRON' && $vaCur === 'USDC_TRON') {
            return true;
        }
        if ($theirUpper === 'USDT_BSC' && $vaCur === 'USDT_BSC') {
            return true;
        }
        if ($theirUpper === 'USDC_BSC' && $vaCur === 'USDC_BSC') {
            return true;
        }

        if ($vaCur === 'USDT_TRON' && $their !== '' && strcasecmp($their, self::TRON_MAINNET_USDT_CONTRACT) === 0) {
            return true;
        }
        if ($vaCur === 'USDC_TRON' && $their !== '' && strcasecmp($their, self::TRON_MAINNET_USDC_CONTRACT) === 0) {
            return true;
        }

        if (in_array($vaCur, ['USDT', 'USDT_ETH'], true) && $their !== '' && $this->addressesEqual($their, self::ETH_MAINNET_USDT_CONTRACT)) {
            return true;
        }
        if (in_array($vaCur, ['USDC', 'USDC_ETH'], true) && $their !== '' && $this->addressesEqual($their, self::ETH_MAINNET_USDC_CONTRACT)) {
            return true;
        }

        if (in_array($vaCur, ['USDT_BSC'], true) && $their !== '' && $this->addressesEqual($their, self::BSC_MAINNET_USDT_CONTRACT)) {
            return true;
        }
        if (in_array($vaCur, ['USDC_BSC'], true) && $their !== '' && $this->addressesEqual($their, self::BSC_MAINNET_USDC_CONTRACT)) {
            return true;
        }

        return false;
    }

    private function isV2OnChainDeposit(DepositAddress $deposit, VirtualAccount $account): bool
    {
        if (($deposit->version ?? '') !== 'v2') {
            return false;
        }
        if ($account->is_tatum_ledger !== false) {
            return false;
        }
        $user = $account->relationLoaded('user') ? $account->user : $account->user()->first();

        return $user && $user->wallet_flow_version === 'v2';
    }

    /** EVM 0x hex (checksum-safe); TRON base58 uses case-sensitive compare. */
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
}
