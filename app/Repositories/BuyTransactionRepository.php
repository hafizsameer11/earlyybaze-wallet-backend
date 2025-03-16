<?php

namespace App\Repositories;

use App\Models\BuyTransaction;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Services\transactionService;

class BuyTransactionRepository
{
    protected $transactionService;
    public function __construct(transactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        return BuyTransaction::with('transaction', 'bankAccount')->find($id);
    }
    public function findByTransactionId($id)
    {
        $transaction= BuyTransaction::with('transaction', 'bankAccount')->where('transaction_id', $id)->first();
        if(!$transaction){
            throw new \Exception('Transaction not found or Id not found'. $id);
        }
        return $transaction;
    }

    public function create(array $data)
    {
        $refference = 'EarlyBaze' . time();
        try {
            $transaction = $this->transactionService->create([
                'type' => 'buy',
                'amount' => $data['amount_coint'] ?? 0,
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
        $data['receipt_attached']=true;
        $buyTransaction->update($data);
        return $buyTransaction;
    }

    public function update($id, array $data)
    {
        // Add logic to update data
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
        return  [
            'assets' => $virtualAccounts,
            'transactions' => $transactions
        ];
    }
}
