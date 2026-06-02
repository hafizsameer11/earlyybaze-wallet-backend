<?php

namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Models\Fee;
use App\Models\MinimumTrade;
use App\Models\SwapTransaction;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Services\NotificationService;
use App\Services\TatumService;
use App\Services\transactionService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SwapTransactionRepository
{
    protected $transactionService, $tatumService;

    public function __construct(transactionService $transactionService, TatumService $tatumService)
    {
        $this->transactionService = $transactionService;
        $this->tatumService = $tatumService;
    }

    public function swap(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $currency = $data['currency'];
                $network = $data['network'];
                $amount = (string) $data['amount'];
                if (bccomp($amount, '0', 8) <= 0) {
                    throw new Exception('Amount must be greater than zero');
                }

                $fee = Fee::where('type', 'swap')->orderBy('created_at', 'desc')->first();
                if (! $fee) {
                    throw new Exception('Swap fee not found.');
                }
                $fixedFee = $fee->amount;
                $percentageFee = $fee->percentage;

                $percentageFeeAmount = bcmul($amount, $percentageFee, 8);
                $percentageFeeConverted = bcdiv($percentageFeeAmount, 100, 8);
                $totalFee = bcadd($fixedFee, $percentageFeeConverted, 8);

                $exchangeRate = ExchangeRate::where('currency', $currency)->orderBy('created_at', 'desc')->firstOrFail();
                $exchangeRatenaira = ExchangeRate::where('currency', 'NGN')->latest()->firstOrFail();
                Log::info('exchange rate', [$exchangeRate]);

                $miniumumTrade = MinimumTrade::where('type', 'swap')->latest()->first();
                if (! $miniumumTrade) {
                    throw new Exception('Minimum trade amount not found.');
                }
                $miniumumTradeAmount = $miniumumTrade->amount;

                if (bccomp((string) $exchangeRate->rate_usd, '0', 8) <= 0) {
                    throw new Exception('Invalid asset USD rate for '.$currency.'.');
                }
                if (bccomp((string) $exchangeRatenaira->rate_naira, '0', 8) <= 0) {
                    throw new Exception('Invalid NGN exchange rate.');
                }

                $feeCurrency = bcdiv($totalFee, $exchangeRate->rate_usd, 8);
                $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
                $amountNaira = bcmul($amountUsd, (string) $exchangeRatenaira->rate_naira, 8);
                $feeNaira = bcmul($totalFee, (string) $exchangeRatenaira->rate, 8);

                $this->assertSwapAmountsAreSane(
                    $currency,
                    (string) $amount,
                    $amountUsd,
                    $amountNaira,
                    (string) $exchangeRatenaira->rate_naira,
                    $exchangeRate
                );

                if (bccomp($amountUsd, $miniumumTradeAmount, 8) < 0) {
                    throw new Exception('Minimum trade amount is '.$miniumumTradeAmount);
                }

                $data['amount_usd'] = $amountUsd;
                $data['amount_naira'] = $amountNaira;
                $data['fee_naira'] = $feeNaira;
                $data['fee'] = $feeCurrency;
                $data['exchange_rate'] = $exchangeRatenaira->rate_naira;

                $reference = 'EarlyBaze'.time();
                Log::info('swap data', [$data]);

                $user = Auth::user();

                $userVirtualAccount = VirtualAccount::where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('blockchain', $network)
                    ->lockForUpdate()
                    ->firstOrFail();

                $totalToDeduct = bcadd($amount, $feeCurrency, 8);
                Log::info('total to deduct', [$totalToDeduct]);

                $currentBalance = (string) $userVirtualAccount->available_balance;
                if (bccomp($currentBalance, $totalToDeduct, 8) < 0) {
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
                        'Your '.$swapTransaction->currency.' swap is pending. You will receive ₦'.$swapTransaction->amount_naira.' when completed.',
                        'swap_pending'
                    );
                });

                return $swapTransaction;
            });
        } catch (Exception $e) {
            Log::error('Swap Failed: '.$e->getMessage());
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
        $swap->symbol = $currencySymbol->symbol;

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

                $userId = $swap->user_id;

                $va = VirtualAccount::where('user_id', $userId)
                    ->where('currency', $swap->currency)
                    ->where('blockchain', $swap->network)
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = (string) $swap->amount;
                $amountNaira = (string) $swap->amount_naira;

                $ngnRow = ExchangeRate::where('currency', 'NGN')->latest()->first();
                if ($ngnRow) {
                    $this->assertSwapAmountsAreSane(
                        (string) $swap->currency,
                        (string) $swap->amount,
                        (string) $swap->amount_usd,
                        $amountNaira,
                        (string) $ngnRow->rate_naira,
                        ExchangeRate::where('currency', $swap->currency)->orderByDesc('created_at')->first()
                    );
                }

                if (stripos($amount, 'e') !== false) {
                    $amount = sprintf('%.8f', (float) $amount);
                }

                if (bccomp($va->available_balance, $amount, 8) < 0) {
                    throw new Exception('Insufficient balance during completion.');
                }

                $va->available_balance = bcsub($va->available_balance, $amount, 8);
                $va->save();

                $ua = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
                if (! $ua) {
                    $ua = new UserAccount(['user_id' => $userId, 'naira_balance' => '0.00000000']);
                }
                $ua->naira_balance = bcadd((string) $ua->naira_balance, $amountNaira, 8);
                $ua->save();

                $updated = SwapTransaction::where('id', $id)->where('status', 'pending')
                    ->update(['status' => 'completed']);
                if ($updated !== 1) {
                    throw new Exception('Swap completion lost the status race.');
                }

                DB::afterCommit(function () use ($id) {
                    $fresh = SwapTransaction::find($id);
                    app(\App\Services\ReferralEarningServiceNew::class)->creditOnSwapCompleted($fresh);
                    if ($fresh) {
                        app(NotificationService::class)->notifyUser(
                            (int) $fresh->user_id,
                            'Swap completed',
                            'Your swap was completed. ₦'.$fresh->amount_naira.' has been credited to your naira wallet.',
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

    /**
     * Safety guard — blocks bad credits before they hit user wallets.
     * Catches corrupted rate_usd (e.g. USDT treated as $127/coin → 78M naira).
     */
    private function assertSwapAmountsAreSane(
        string $currency,
        string $coinAmount,
        string $amountUsd,
        string $amountNaira,
        string $ngnPerUsd,
        ?ExchangeRate $cryptoRow = null
    ): void {
        if (bccomp($amountUsd, '0', 8) <= 0 || bccomp($coinAmount, '0', 8) <= 0) {
            throw new Exception('Invalid swap amount.');
        }
        if (bccomp($ngnPerUsd, '0', 8) <= 0) {
            throw new Exception('Invalid NGN exchange rate.');
        }

        $expectedNaira = bcmul($amountUsd, $ngnPerUsd, 8);
        $nairaDiff = bcsub($amountNaira, $expectedNaira, 8);
        if ($nairaDiff[0] === '-') {
            $nairaDiff = substr($nairaDiff, 1);
        }
        $nairaTolerance = bcadd(bcmul($expectedNaira, '0.01', 8), '1', 8);
        if (bccomp($nairaDiff, $nairaTolerance, 8) > 0) {
            Log::error('Swap naira mismatch blocked', compact('currency', 'coinAmount', 'amountUsd', 'amountNaira', 'ngnPerUsd', 'expectedNaira'));
            throw new Exception('Swap naira amount does not match the NGN exchange rate.');
        }

        if ($this->isUsdStablecoin($currency)) {
            $usdPerCoin = bcdiv($amountUsd, $coinAmount, 8);
            if (bccomp($usdPerCoin, '0.95', 8) < 0 || bccomp($usdPerCoin, '1.05', 8) > 0) {
                Log::error('Stablecoin rate_usd out of range — swap blocked', [
                    'currency' => $currency,
                    'coinAmount' => $coinAmount,
                    'amountUsd' => $amountUsd,
                    'usdPerCoin' => $usdPerCoin,
                    'crypto_rate_usd' => $cryptoRow?->rate_usd,
                ]);
                throw new Exception('Invalid stablecoin USD rate for this asset. Please contact support.');
            }
        }
    }

    private function isUsdStablecoin(string $currency): bool
    {
        $c = strtoupper($currency);

        return str_contains($c, 'USDT')
            || str_contains($c, 'USDC')
            || str_contains($c, 'DAI')
            || str_contains($c, 'BUSD');
    }
}
