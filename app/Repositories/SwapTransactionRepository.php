<?php

namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Models\Fee;
use App\Models\SwapTransaction;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
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
            // $fee = $data['fee'];
            $fee = Fee::where('type', 'swap')->orderBy('created_at', 'desc')->first();
            // $fee=$fee->rate;
            $feea = $fee->amount;
            $feePercentage = $fee->percentage;
            $feePercentageAmount = bcmul($amount, $feePercentage, 8);
            $feeAmount = bcdiv($feePercentageAmount, 100, 8);
            $fee = bcadd($feea, $feeAmount, 8);

            // Fetch the latest exchange rate
            $exchangeRate = ExchangeRate::where('currency', $currency)
                ->orderBy('created_at', 'desc')
                ->first();
            $feeCurrency = bcdiv($fee, $exchangeRate->rate_usd, 8);
            $exchangeRatenaira = ExchangeRate::where('currency', 'NGN')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$exchangeRate) {
                throw new Exception('Exchange rate unavailable. Please try again later.');
            }

            // Convert amounts to USD and NGN (Naira)
            $amount_usd = bcmul($amount, $exchangeRate->rate_usd, 8);
            $amount_naira = bcmul($amount, $exchangeRate->rate_naira, 8);
            $fee_naira = bcmul($feea, $exchangeRatenaira->rate, 8);

            // Add calculated values to data
            $data['amount_usd'] = $amount_usd;
            $data['amount_naira'] = $amount_naira;
            $data['fee_naira'] = $fee_naira;
            $data['fee'] = $feeCurrency;

            // Generate unique transaction reference
            $reference = 'EarlyBaze' . time();

            $admin = User::where('email', 'admin@gmail.com')->first();
            if (!$admin) {
                throw new Exception('Admin account not found.');
            }

            $adminVirtualAccount = VirtualAccount::where('user_id', $admin->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$adminVirtualAccount) {
                throw new Exception('Swap is currently unavailable. Please try again later.');
            }

            // Fetch authenticated user & their Virtual Account
            $user = Auth::user();
            $userVirtualAccount = VirtualAccount::where('user_id', $user->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$userVirtualAccount) {
                throw new Exception('Your virtual account was not found.');
            }
            $finalAmount = bcadd($amount, $feeCurrency, 8);
            if ($userVirtualAccount->available_balance < $finalAmount) {
                throw new Exception('Insufficient Balance for swap.');
            }

            // Execute internal transfer from user to admin
            $response = $this->tatumService->transferFunds(
                $userVirtualAccount->account_id,
                $adminVirtualAccount->account_id,
                $finalAmount,
                $currency
            );
            Log::info('Swap Internal Transfer Response: ' . json_encode($response));
            $status = 'failed';
            $txId = null;
            $userAccount = UserAccount::where('user_id', $user->id)->first();
            if (isset($response['reference'])) {
                $status = 'completed';
                $txId = $response['reference'];
                $userVirtualAccount->available_balance = bcsub($userVirtualAccount->available_balance, $finalAmount, 8);
                $userVirtualAccount->save();
                $adminVirtualAccount->available_balance = bcadd($adminVirtualAccount->available_balance, $finalAmount, 8);
                $adminVirtualAccount->save();
                $userAccount->naira_balance = bcadd($userAccount->naira_balance, $amount_naira, 8);
                $userAccount->save();
            } elseif (isset($response['errorCode']) && $response['errorCode'] === "balance.insufficient") {
                throw new Exception("Insufficient balance: " . $response['message']);
            }

            // Store transaction details
            $transaction = $this->transactionService->create([
                'type' => 'swap',
                'amount' => $finalAmount,
                'currency' => $currency,
                'status' => $status,
                'network' => $network,
                'reference' => $txId,
                'user_id' => $user->id,
                'amount_usd' => $amount_usd
            ]);

            // Store Swap Transaction Record
            $data['status'] = $status;
            $data['user_id'] = $user->id;
            $data['reference'] = $txId;
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
        return SwapTransaction::where('transaction_id', $id)->first();
    }
}
