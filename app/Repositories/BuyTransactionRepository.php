<?php

namespace App\Repositories;

use App\Models\BuyTransaction;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Services\transactionService;
use Illuminate\Support\Facades\Log;

class BuyTransactionRepository
{
    protected $transactionService;
    public function __construct(transactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function getAllBuyRequests()
    {
        $buyRequests = BuyTransaction::with('transaction', 'bankAccount', 'user')->orderBy('created_at', 'desc')->get();
        return $buyRequests;
    }
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        $buy = BuyTransaction::where('transaction_id', $id)->with('transaction', 'bankAccount')->first();
        if (!$buy) {
            throw new \Exception('Transaction not found or Id not found' . $id);
        }
        //   return $buy
        $formattedResponse = [
            'coin' => ucfirst($buy->currency),  // Making the currency name capitalized
            'network' => ucfirst($buy->network),
            'amount_btc' => $buy->amount_coin . ' BTC',
            'amount_usd' => '$' . number_format($buy->amount_usd, 2),
            'amount_paid' => $buy->amount_naira ? 'NGN' . number_format($buy->amount_naira, 2) : 'N/A',
            'account_paid_to' => $buy->bankAccount ? $buy->bankAccount->account_name . ' (' . $buy->bankAccount->bank_name . ')' : 'N/A',
            'transaction_reference' => $buy->transaction ? $buy->transaction->reference : 'N/A',
            'transaction_date' => $buy->created_at,
            'status' => ucfirst($buy->status),
            'created_at' => $buy->created_at,
        ];
        return $formattedResponse;
        //   return $buy;
    }
    public function findByTransactionId($id)
    {
        $transaction = BuyTransaction::with('transaction', 'bankAccount')->where('transaction_id', $id)->first();
        if (!$transaction) {
            throw new \Exception('Transaction not found or Id not found' . $id);
        }
        return $transaction;
    }

    public function create(array $data)
    {
        $refference = 'EarlyBaze' . time();
        Log::info('Buy Transaction', $data);
        try {
            $transaction = $this->transactionService->create([
                'type' => 'buy',
                'amount' => $data['amount_coin'] ?? 0,
                'amount_usd' => $data['amount_usd'] ?? 0,
                'currency' => $data['currency'] ?? null,
                'network' => $data['network'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'status' => 'pending',
                'reference' => $refference,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $data['transaction_id'] = $transaction->id;
        $data['reference'] = $refference;
        $data['status'] = 'pending';
        $buyTransaction = BuyTransaction::create($data);
        return $buyTransaction->load('transaction', 'bankAccount');
    }
    public function attachSlip($id, array $data)
    {
        $buyTransaction = BuyTransaction::find($id);
        if (!$buyTransaction) {
            throw new \Exception('Buy Transaction not found');
        }
        if (isset($data['receipt']) && $data['receipt']) {
            $path = $data['receipt']->store('receipts', 'public');
        }
        $data['receipt'] = $path;
        $data['receipt_attached'] = true;
        $buyTransaction->update($data);
        return $buyTransaction;
    }

    public function update($id, array $data)
    {
        // Aupdate statius and add rejection_reason
        $buyTransaction = BuyTransaction::find($id);
        if (!$buyTransaction) {
            throw new \Exception('Buy Transaction not found');
        }
        if (isset($data['status'])) {
            $buyTransaction->update(['status' => $data['status']]);
        }
        if (isset($data['rejection_reason'])) {
            $buyTransaction->update(['rejection_reason' => $data['rejection_reason']]);
        }
        $user_id = $buyTransaction->user_id;
        $virtualAccount = VirtualAccount::where('user_id', $user_id)->where('currency', $buyTransaction->currency)->orderBy('created_at', 'desc')->first();
        if ($virtualAccount) {
            $virtualAccount->update([
                'available_balance' => $virtualAccount->available_balance + $buyTransaction->amount_coin,
                'account_balance' => $virtualAccount->account_balance + $buyTransaction->amount_coin,
            ]);
        }
        return $buyTransaction;
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function getUserAssetTransactions($userId)
    {
        $virtualAccounts = VirtualAccount::where('user_id', $userId)->with('walletCurrency', 'depositAddresses')->get();
        $userAccount = UserAccount::where('user_id', $userId)->first();
        $virtualAccounts = $virtualAccounts->map(function ($account) use ($userAccount) {
            $exchangeRate = ExchangeRate::where('currency', $account->currency)->orderBy('created_at', 'desc')->first();
            $price = '';
            if ($exchangeRate) {
                $price = "1 $account->currency = $exchangeRate->rate_usd USD";
            }
            return [
                'id' => $account->id,
                'name' => $account->currency,
                'symbol' => $account->walletCurrency->symbol,
                'icon' => $account->walletCurrency->icon,
                'balance' => $account->available_balance,
                'account_balance' => $account->account_balance,
                'price' => $price,
            ];
        });
        $transactions = Transaction::where('user_id', $userId)->orderBy('created_at', 'desc')->take(4)->get();
        $transactions = $transactions->map(function ($transaction) {
            //merge all data by just adding symbol of currency
            $currency = WalletCurrency::where('currency', $transaction->currency)->first();
            $symbol = '';
            if ($currency) {
                $symbol = $currency->symbol;
            }
            return [
                'id' => (int)$transaction->id,
                'name' => $transaction->currency,
                'symbol' => $symbol,
                'icon' => $symbol,
                'balance' => $transaction->amount,
                'created_at' => $transaction->created_at,
                'type' => $transaction->type,
            ];
        });
        return  [
            'assets' => $virtualAccounts,
            'transactions' => $transactions
        ];
    }
}
