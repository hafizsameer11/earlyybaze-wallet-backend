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

        $lockKey = 'webhook_lock_'.$reference;
        $lock = Cache::lock($lockKey, 120);
        if (! $lock->get()) {
            Log::warning('v4 webhook lock busy', ['reference' => $reference]);

            return;
        }

        try {
            $amount = (string) ($data['amount'] ?? '0');
            $currency = strtoupper((string) ($data['currency'] ?? $account->currency));
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
            ->with(['virtualAccount.walletCurrency'])
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
            if (strtoupper((string) $va->currency) !== $currency) {
                continue;
            }

            if ($subType === 'INCOMING_FUNGIBLE_TX') {
                if (! $this->fungibleContractMatches($va, $data['contractAddress'] ?? null)) {
                    continue;
                }
            } elseif ($subType === 'INCOMING_NATIVE_TX') {
                $wc = $va->walletCurrency;
                if ($wc && ($wc->is_token ?? false)) {
                    continue;
                }
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

        $their = strtoupper(trim((string) $payloadContract));
        $ours = strtoupper(trim((string) ($wc->contract_address ?? '')));

        if ($ours !== '' && $their !== '' && strtolower($ours) === strtolower($their)) {
            return true;
        }

        if ($their === 'USDT_TRON' && strtoupper((string) $va->currency) === 'USDT_TRON') {
            return true;
        }

        if ($their === 'USDC_TRON' && strtoupper((string) $va->currency) === 'USDC_TRON') {
            return true;
        }

        return false;
    }
}
