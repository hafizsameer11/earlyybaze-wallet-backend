<?php

namespace App\Repositories\V3;

use App\Models\ExchangeRate;
use App\Models\Fee;
use App\Models\MinimumTrade;
use App\Models\SwapTransaction;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Services\NotificationService;
use App\Services\transactionService;
use App\Support\FiatExchangeHelper;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ZAR (multi-fiat) swap — v3 only.
 * Legacy NGN swaps use App\Repositories\SwapTransactionRepository unchanged.
 */
class V3SwapTransactionRepository
{
    public function __construct(
        protected transactionService $transactionService,
    ) {}

    public function swap(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $currency = $data['currency'];
                $network = $data['network'];
                $amount = $data['amount'];
                $fiatCurrency = FiatExchangeHelper::normalizeFiat($data['fiat_currency'] ?? 'ZAR');

                if ($fiatCurrency !== 'ZAR') {
                    throw new Exception('V3 swap route supports ZAR only. Use legacy /wallet/swap for NGN.');
                }

                $fee = Fee::where('type', 'swap')->orderByDesc('created_at')->first();
                if (! $fee) {
                    throw new Exception('Swap fee not found.');
                }

                $percentageFeeAmount = bcmul($amount, (string) $fee->percentage, 8);
                $percentageFeeConverted = bcdiv($percentageFeeAmount, '100', 8);
                $totalFee = bcadd((string) $fee->amount, $percentageFeeConverted, 8);

                $exchangeRate = ExchangeRate::where('currency', $currency)->orderByDesc('created_at')->firstOrFail();
                $zarRow = ExchangeRate::where('currency', 'ZAR')->latest()->firstOrFail();

                if (bccomp((string) $exchangeRate->rate_usd, '0', 8) <= 0) {
                    throw new Exception('Invalid asset USD rate for '.$currency.'.');
                }

                $feeCurrency = bcdiv($totalFee, (string) $exchangeRate->rate_usd, 8);
                $amountUsd = bcmul($amount, (string) $exchangeRate->rate_usd, 8);
                $amountZar = FiatExchangeHelper::usdToFiatViaCryptoRow($amountUsd, $exchangeRate, 'ZAR');
                $feeZar = bcmul($totalFee, (string) $zarRow->rate, 8);

                $miniumumTrade = MinimumTrade::where('type', 'swap')->latest()->first();
                if (! $miniumumTrade) {
                    throw new Exception('Minimum trade amount not found.');
                }
                if (bccomp($amountUsd, (string) $miniumumTrade->amount, 8) < 0) {
                    throw new Exception('Minimum trade amount is '.$miniumumTrade->amount);
                }

                $data['amount_usd'] = $amountUsd;
                $data['amount_naira'] = '0';
                $data['amount_zar'] = $amountZar;
                $data['fiat_currency'] = 'ZAR';
                $data['fee_naira'] = $feeZar;
                $data['fee'] = $feeCurrency;
                $data['exchange_rate'] = $zarRow->rate;

                $reference = 'EarlyBaze'.time();
                $user = Auth::user();

                $userVirtualAccount = VirtualAccount::where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('blockchain', $network)
                    ->lockForUpdate()
                    ->firstOrFail();

                $totalToDeduct = bcadd($amount, $feeCurrency, 8);
                if (bccomp((string) $userVirtualAccount->available_balance, $totalToDeduct, 8) < 0) {
                    throw new Exception('Insufficient balance for swap.');
                }

                $transaction = $this->transactionService->create([
                    'type' => 'swap',
                    'amount' => $totalToDeduct,
                    'currency' => $currency,
                    'status' => 'completed',
                    'network' => $network,
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'amount_usd' => $amountUsd,
                ]);

                $data['status'] = 'pending';
                $data['user_id'] = $user->id;
                $data['reference'] = $reference;
                $data['transaction_id'] = $transaction->id;

                $swapTransaction = SwapTransaction::create($data);

                DB::afterCommit(function () use ($swapTransaction) {
                    app(NotificationService::class)->notifyUser(
                        (int) $swapTransaction->user_id,
                        'Swap submitted',
                        'Your '.$swapTransaction->currency.' swap is pending. You will receive R'.$swapTransaction->amount_zar.' when completed.',
                        'swap_pending'
                    );
                });

                return $swapTransaction;
            });
        } catch (Exception $e) {
            Log::error('V3 Swap Failed: '.$e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    public function singleSwapTransaction($id)
    {
        $swap = SwapTransaction::where('transaction_id', $id)->first();
        if (! $swap) {
            throw new Exception('Transaction not found.');
        }
        $currencySymbol = WalletCurrency::where('currency', $swap->currency)->first();
        $swap->symbol = $currencySymbol?->symbol;

        return $swap;
    }

    public function completeSwapTransaction($id)
    {
        $attempts = 0;
        retry:
        try {
            return DB::transaction(function () use ($id) {
                $swap = SwapTransaction::where('id', $id)->lockForUpdate()->first();
                if (! $swap) {
                    throw new Exception('Transaction not found.');
                }

                if ($swap->status !== 'pending') {
                    return $swap;
                }

                if (strtoupper((string) ($swap->fiat_currency ?? '')) !== 'ZAR') {
                    throw new Exception('This swap is not a ZAR swap. Use legacy completion for NGN.');
                }

                $userId = $swap->user_id;
                $va = VirtualAccount::where('user_id', $userId)
                    ->where('currency', $swap->currency)
                    ->where('blockchain', $swap->network)
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = (string) $swap->amount;
                $amountZar = (string) $swap->amount_zar;

                if (stripos($amount, 'e') !== false) {
                    $amount = sprintf('%.8f', (float) $amount);
                }

                if (bccomp((string) $va->available_balance, $amount, 8) < 0) {
                    throw new Exception('Insufficient balance during completion.');
                }

                $va->available_balance = bcsub((string) $va->available_balance, $amount, 8);
                $va->save();

                $ua = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
                if (! $ua) {
                    $ua = new UserAccount([
                        'user_id' => $userId,
                        'naira_balance' => '0.00000000',
                        'zar_balance' => '0.00000000',
                    ]);
                }
                $ua->zar_balance = bcadd((string) ($ua->zar_balance ?? '0'), $amountZar, 8);
                $ua->save();

                $updated = SwapTransaction::where('id', $id)->where('status', 'pending')
                    ->update(['status' => 'completed']);
                if ($updated !== 1) {
                    throw new Exception('Swap completion lost the status race.');
                }

                DB::afterCommit(function () use ($id) {
                    $fresh = SwapTransaction::find($id);
                    if ($fresh) {
                        app(\App\Services\ReferralEarningServiceNew::class)->creditOnSwapCompleted($fresh);
                        app(NotificationService::class)->notifyUser(
                            (int) $fresh->user_id,
                            'Swap completed',
                            'Your swap was completed. R'.$fresh->amount_zar.' has been credited to your rand wallet.',
                            'swap_completed'
                        );
                    }
                });

                return $swap->fresh();
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Deadlock found') && $attempts++ < 3) {
                usleep(100000);
                goto retry;
            }
            throw $e;
        }
    }
}
