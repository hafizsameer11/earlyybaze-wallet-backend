<?php

namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Models\Fee;
use App\Models\MinimumTrade;
use App\Models\SwapTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
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
            // Use database transaction to ensure atomicity and prevent race conditions
            return DB::transaction(function () use ($data) {
                // Extract necessary parameters
                $currency = $data['currency'];
                $network = $data['network'];
                $amount = $data['amount'];

                // Fetch fee
                $fee = Fee::where('type', 'swap')->orderBy('created_at', 'desc')->first();
                if (!$fee) {
                    throw new Exception('Swap fee not found.');
                }
                $fixedFee = $fee->amount;
                $percentageFee = $fee->percentage;

                // Calculate fee
                $percentageFeeAmount = bcmul($amount, $percentageFee, 8);
                $percentageFeeConverted = bcdiv($percentageFeeAmount, 100, 8);
                $totalFee = bcadd($fixedFee, $percentageFeeConverted, 8);

                // Fetch exchange rate
                $exchangeRate = ExchangeRate::where('currency', $currency)->orderBy('created_at', 'desc')->firstOrFail();
                $exchangeRatenaira = ExchangeRate::where('currency', 'NGN')->latest()->firstOrFail();
                Log::info("exchange rate", [$exchangeRate]);
                // Fee in token and converted currencies
                $miniumumTrade = MinimumTrade::where('type', 'swap')->latest()->first();
                if (!$miniumumTrade) {
                    throw new Exception('Minimum trade amount not found.');
                }
                $miniumumTradeAmount = $miniumumTrade->amount;
                $feeCurrency = bcdiv($totalFee, $exchangeRate->rate_usd, 8);
                $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
                $amountNaira = bcmul($amountUsd, $exchangeRatenaira->rate_naira, 8);
                $feeNaira = bcmul($totalFee, $exchangeRatenaira->rate, 8);
                if (bccomp($amountUsd, $miniumumTradeAmount, 8) < 0) {
                    throw new Exception('Minimum trade amount is ' . $miniumumTradeAmount);
                }
                // Add calculated values to data
                $data['amount_usd'] = $amountUsd;
                $data['amount_naira'] = $amountNaira;
                $data['fee_naira'] = $feeNaira;
                $data['fee'] = $feeCurrency;
                $data['exchange_rate'] = $exchangeRatenaira->rate_naira;

                $reference = 'EarlyBaze' . time();
                Log::info("swap data", [$data]);
                // Get admin and user
                // $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
                $user = Auth::user();

                // Lock the virtual account row to prevent concurrent swaps
                $userVirtualAccount = VirtualAccount::where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('blockchain', $network)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Calculate total to deduct (amount + fee)
                $totalToDeduct = bcadd($amount, $feeCurrency, 8);
                Log::info("total to deduct", [$totalToDeduct]);
                
                // Get current available balance
                $currentBalance = (string) $userVirtualAccount->available_balance;
                
                // Check if current balance is sufficient (without checking pending swaps)
                if (bccomp($currentBalance, $totalToDeduct, 8) < 0) {
                    throw new Exception('Insufficient balance for swap.');
                }

                // Note: Balance is NOT deducted here - it's deducted in completeSwapTransaction()
                // This is intentional as the swap is created with 'pending' status first

                // Save transaction record
                $transaction = $this->transactionService->create([
                    'type' => 'swap',
                    'amount' => $totalToDeduct,
                    'currency' => $currency,
                    'status' => 'completed',
                    'network' => $network,
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'amount_usd' => $amountUsd
                ]);

                // Save swap transaction
                $data['status'] = 'pending';
                $data['user_id'] = $user->id;
                $data['reference'] = $reference;
                $data['transaction_id'] = $transaction->id;
                $swapTransaction = SwapTransaction::create($data);

                return $swapTransaction;
            });
        } catch (Exception $e) {
            Log::error("Swap Failed: " . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    public function singleSwapTransaction($id)
    {
        $swap = SwapTransaction::where('transaction_id', $id)->first();
        //add currency symbol with it
        if (!$swap) {
            throw new Exception('Transaction not found.');
        }
        $currencySymbol = WalletCurrency::where('currency', $swap->currency)->first();
        $swap->symbol = $currencySymbol->symbol;
        return $swap;
    }
    // public function completeSwapTransaction($id)
    // {
    //     $swap = SwapTransaction::where('id', $id)->first();
    //     if (!$swap) {
    //         throw new Exception('Transaction not found.');
    //     }
    //     $user = Auth::user();
    //     $userVirtualAccount = VirtualAccount::where('user_id', $user->id)
    //         ->where('currency', $swap->currency)
    //         ->where('blockchain', $swap->network)
    //         ->firstOrFail();
    //     $amountNaira = $swap->amount_naira;
    //     $amount = $swap->amount;

    //     Log::info("bcsub inputs", [
    //         'available_balance' => $userVirtualAccount->available_balance,
    //         'amount_raw' => $amount,
    //         'amount_dump' => var_export($amount, true)
    //     ]);
    //     if (strpos(strtolower($amount), 'e') !== false) {
    //         $amount = sprintf('%.8f', (float) $amount); // or more decimal precision as needed
    //     }

    //     $userVirtualAccount->available_balance = bcsub($userVirtualAccount->available_balance, $amount, 8);


    //     $userVirtualAccount->save();
    //     $userAccount = UserAccount::where('user_id', $user->id)->first();
    //     if ($userAccount) {
    //         $userAccount->naira_balance = bcadd($userAccount->naira_balance, $amountNaira, 8);
    //         $userAccount->save();
    //     }
    //     $swap->status = 'completed';
    //     $swap->save();
    //     app(\App\Services\ReferralEarningServiceNew::class)->creditOnSwapCompleted($swap);

    //     return $swap;
    // }

public function completeSwapTransaction($id)
{
    $attempts = 0;
    retry:
    try {
        return DB::transaction(function () use ($id) {
            // 1) lock swap
            $swap = SwapTransaction::where('id', $id)->lockForUpdate()->first();
            if (!$swap) throw new Exception('Transaction not found.');

            if ($swap->status !== 'pending') return $swap;

            $userId = $swap->user_id;

            // 2) lock VA
            $va = VirtualAccount::where('user_id', $userId)
                ->where('currency', $swap->currency)
                ->where('blockchain', $swap->network)
                ->lockForUpdate()
                ->firstOrFail();

            // 3) normalize amounts
            $amount       = (string)$swap->amount;
            $amountNaira  = (string)$swap->amount_naira;

            if (stripos($amount, 'e') !== false) {
                $amount = sprintf('%.8f', (float)$amount);
            }

            // TODO: if fee must also be deducted, compute $totalToDeduct here and use it instead of $amount
            if (bccomp($va->available_balance, $amount, 8) < 0) {
                throw new Exception('Insufficient balance during completion.');
            }

            // 4) apply VA debit
            $va->available_balance = bcsub($va->available_balance, $amount, 8);
            $va->save();

            // 5) lock/create user account and credit NGN
            $ua = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
            if (!$ua) {
                $ua = new UserAccount(['user_id' => $userId, 'naira_balance' => '0.00000000']);
            }
            $ua->naira_balance = bcadd($ua->naira_balance, $amountNaira, 8);
            $ua->save();

            // 6) flip statuses atomically
            $updated = SwapTransaction::where('id', $id)->where('status', 'pending')
                ->update(['status' => 'completed']);
            if ($updated !== 1) throw new Exception('Swap completion lost the status race.');

            // If you also have a transactions row:
            // Transaction::where('id', $swap->transaction_id)->where('status', 'pending')
            //     ->update(['status' => 'completed']);

            DB::afterCommit(function () use ($id) {
                $fresh = SwapTransaction::find($id);
                app(\App\Services\ReferralEarningServiceNew::class)->creditOnSwapCompleted($fresh);
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
