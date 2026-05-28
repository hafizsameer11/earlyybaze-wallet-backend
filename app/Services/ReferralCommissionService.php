<?php

namespace App\Services;

use App\Models\ReceiveTransaction;
use App\Models\ReferalEarning;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralCommissionService
{
    public const DEFAULT_CURRENCY = 'USDT_TRON';

    public const MIN_TRANSFER_USD = '1';

    public function __construct(
        private ExchangeRateService $exchangeRateService,
        private transactionService $transactionService,
        private NotificationService $notificationService,
    ) {}

    public function syncCommissionBalance(int $userId): string
    {
        $pending = (string) ReferalEarning::where('user_id', $userId)
            ->where('status', 'pending')
            ->sum('amount');

        $account = UserAccount::firstOrCreate(['user_id' => $userId]);
        $account->referral_commission_usdt = $pending;
        $account->save();

        return $pending;
    }

    public function getAvailableBalance(int $userId): string
    {
        return $this->syncCommissionBalance($userId);
    }

    public function getTransferOptions(int $userId): array
    {
        $available = $this->getAvailableBalance($userId);

        $accounts = VirtualAccount::where('user_id', $userId)
            ->cryptoOnly()
            ->with('walletCurrency')
            ->get()
            ->sortBy(fn ($account) => $account->currency === self::DEFAULT_CURRENCY ? 0 : 1)
            ->values();

        $destinations = $accounts->map(function (VirtualAccount $account) {
            return [
                'currency' => $account->currency,
                'blockchain' => $account->blockchain,
                'label' => $this->walletLabelFromCurrency($account->currency),
                'balance' => (string) $account->available_balance,
                'is_default' => $account->currency === self::DEFAULT_CURRENCY,
            ];
        })->values()->all();

        return [
            'available_commission_usd' => (float) $available,
            'default_currency' => self::DEFAULT_CURRENCY,
            'minimum_transfer_usd' => (float) self::MIN_TRANSFER_USD,
            'destinations' => $destinations,
        ];
    }

    public function transfer(int $userId, string $amountUsd, string $currency): array
    {
        $currency = strtoupper($currency);
        $amountUsd = bcadd($amountUsd, '0', 8);

        if (bccomp($amountUsd, self::MIN_TRANSFER_USD, 8) < 0) {
            throw new \Exception('Minimum transfer is $1.00 USDT');
        }

        return DB::transaction(function () use ($userId, $amountUsd, $currency) {
            $account = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
            if (! $account) {
                throw new \Exception('User account not found');
            }

            $this->syncCommissionBalance($userId);
            $account->refresh();

            $available = (string) $account->referral_commission_usdt;
            if (bccomp($available, $amountUsd, 8) < 0) {
                throw new \Exception('Insufficient commission balance');
            }

            $virtualAccount = VirtualAccount::where('user_id', $userId)
                ->where('currency', $currency)
                ->cryptoOnly()
                ->lockForUpdate()
                ->first();

            if (! $virtualAccount) {
                throw new \Exception(
                    'Destination wallet not found. Please create a '.$this->walletLabelFromCurrency($currency).' wallet first.'
                );
            }

            $creditAmount = $this->convertUsdToCrypto($amountUsd, $currency);

            $account->referral_commission_usdt = bcsub($available, $amountUsd, 8);
            $account->save();

            $this->consumePendingEarnings($userId, $amountUsd);

            $virtualAccount->available_balance = bcadd((string) $virtualAccount->available_balance, $creditAmount, 8);
            $virtualAccount->account_balance = bcadd((string) $virtualAccount->account_balance, $creditAmount, 8);
            $virtualAccount->save();

            $reference = strtoupper(Str::random(16));
            $transaction = $this->transactionService->create([
                'type' => 'receive',
                'amount' => $creditAmount,
                'currency' => $currency,
                'status' => 'completed',
                'network' => $virtualAccount->blockchain,
                'reference' => $reference,
                'user_id' => $userId,
                'amount_usd' => $amountUsd,
                'transfer_type' => 'referral_commission',
            ]);

            ReceiveTransaction::create([
                'user_id' => $userId,
                'virtual_account_id' => $virtualAccount->id,
                'transaction_id' => $transaction->id,
                'transaction_type' => 'referral_commission',
                'sender_address' => 'commission_wallet',
                'amount' => $creditAmount,
                'currency' => $currency,
                'status' => 'completed',
                'blockchain' => $virtualAccount->blockchain,
                'amount_usd' => $amountUsd,
            ]);

            $this->notificationService->notifyUser(
                $userId,
                'Commission transferred',
                sprintf(
                    'You moved $%s from your commission wallet to %s.',
                    number_format((float) $amountUsd, 2),
                    $this->walletLabelFromCurrency($currency)
                ),
                'referral'
            );

            return [
                'reference' => $reference,
                'amount_usd' => (float) $amountUsd,
                'amount_credited' => $creditAmount,
                'currency' => $currency,
                'destination_label' => $this->walletLabelFromCurrency($currency),
                'commission_balance_remaining' => (float) $account->referral_commission_usdt,
            ];
        });
    }

    private function convertUsdToCrypto(string $amountUsd, string $currency): string
    {
        if (str_starts_with($currency, 'USDT') || str_starts_with($currency, 'USDC')) {
            return $amountUsd;
        }

        $rate = $this->exchangeRateService->getByCurrency($currency);
        if (! $rate || ! (float) $rate->rate_usd) {
            throw new \Exception('Exchange rate not available for '.$currency);
        }

        return bcdiv($amountUsd, (string) $rate->rate_usd, 8);
    }

    private function consumePendingEarnings(int $userId, string $amountUsd): void
    {
        $remaining = $amountUsd;
        $earnings = ReferalEarning::where('user_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($earnings as $earning) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $earningAmount = bcadd((string) $earning->amount, '0', 8);
            if (bccomp($remaining, $earningAmount, 8) >= 0) {
                $earning->status = 'transferred';
                $earning->save();
                $remaining = bcsub($remaining, $earningAmount, 8);
            }
        }

        if (bccomp($remaining, '0', 8) > 0) {
            throw new \Exception('Could not allocate commission earnings for transfer');
        }
    }

    private function walletLabelFromCurrency(string $currency): string
    {
        return match ($currency) {
            'USDT_TRON' => 'USDT (Tron)',
            'USDT_ETH' => 'USDT (Ethereum)',
            'USDT_BSC' => 'USDT (BSC)',
            'USDT_POLYGON' => 'USDT (Polygon)',
            default => str_replace('_', ' ', $currency),
        };
    }
}
