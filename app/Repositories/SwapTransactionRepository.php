<?php

namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Models\Fee;
use App\Models\SwapTransaction;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Services\TatumService;
use App\Services\transactionService;
use Exception;
use Illuminate\Support\Facades\Auth;
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
            // Extract necessary parameters
            $currency = $data['currency'];
            $network = $data['network'];
            $amount = $data['amount'];

            // Fetch fee
            $fee = Fee::where('type', 'swap')->orderBy('created_at', 'desc')->first();
            $fixedFee = $fee->amount;
            $percentageFee = $fee->percentage;

            // Calculate fee
            $percentageFeeAmount = bcmul($amount, $percentageFee, 8);
            $percentageFeeConverted = bcdiv($percentageFeeAmount, 100, 8);
            $totalFee = bcadd($fixedFee, $percentageFeeConverted, 8);

            // Fetch exchange rate
            $exchangeRate = ExchangeRate::where('currency', $currency)->latest()->firstOrFail();
            $exchangeRatenaira = ExchangeRate::where('currency', 'NGN')->latest()->firstOrFail();

            // Fee in token and converted currencies
            $feeCurrency = bcdiv($totalFee, $exchangeRate->rate_usd, 8);
            $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
            $amountNaira = bcmul($amount, $exchangeRate->rate_naira, 8);
            $feeNaira = bcmul($totalFee, $exchangeRatenaira->rate, 8);

            // Add calculated values to data
            $data['amount_usd'] = $amountUsd;
            $data['amount_naira'] = $amountNaira;
            $data['fee_naira'] = $feeNaira;
            $data['fee'] = $feeCurrency;

            $reference = 'EarlyBaze' . time();

            // Get admin and user
            $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
            $user = Auth::user();



            $userVirtualAccount = VirtualAccount::where('user_id', $user->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->firstOrFail();

            // Check user balance
            $totalToDeduct = bcadd($amount, $feeCurrency, 8);
            if (bccomp($userVirtualAccount->available_balance, $totalToDeduct, 8) < 0) {
                throw new Exception('Insufficient balance for swap.');
            }

           $userVirtualAccount->available_balance = bcsub($userVirtualAccount->available_balance, $totalToDeduct, 8);
            $userVirtualAccount->save();
            $userAccount = UserAccount::where('user_id', $user->id)->first();
            if ($userAccount) {
                $userAccount->naira_balance = bcadd($userAccount->naira_balance, $amountNaira, 8);
                $userAccount->save();
            }

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
    public function completeSwapTransaction($id)
    {
        $swap = SwapTransaction::where('transaction_id', $id)->first();
        if (!$swap) {
            throw new Exception('Transaction not found.');
        }
        $swap->status = 'completed';
        $swap->save();
        return $swap;
    }
}
