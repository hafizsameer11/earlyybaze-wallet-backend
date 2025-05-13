<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\TransactionIcon;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;

class transactionRepository
{
    public function all()
    {
        $totalTransactions = Transaction::count();
        $totalWallets = VirtualAccount::count();

        $transactions = Transaction::with([
            'user',
            'sendtransaction',
            'recievetransaction',
            'buytransaction',
            'swaptransaction',
            'withdraw_transaction.withdraw_request',
        ])->orderBy('created_at', 'desc')->get();
        return ['transactions' => $transactions, 'totalTransactions' => $totalTransactions, 'totalWallets' => $totalWallets];
    }

    public function find($id)
    {
        // Add logic to find data  by ID
    }
    // use Illuminate\Support\Facades\DB;

    public function getTransactionsForUser($user_id)
    {
        // Totals
        $totalTransactions = Transaction::where('user_id', $user_id)->whereNotIn('type', ['withdrawTransaction'])->count();
        $totalWallets = VirtualAccount::where('user_id', $user_id)->count();

        // Transaction list
        $transactions = Transaction::where('user_id', $user_id)->
        with([
            'user',
            'sendtransaction',
            'recievetransaction',
            'buytransaction',
            'swaptransaction',
        ])->orderBy('created_at', 'desc')->get();

        // Graphical Data (monthly grouped by type)
        $rawStats = DB::table('transactions')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%b, %y') as month"),
                'type',
                DB::raw("COUNT(*) as total")
            )
            ->where('user_id', $user_id)
            ->groupBy('month', 'type')
            ->orderByRaw("STR_TO_DATE(CONCAT('01 ', month), '%d %b, %y')")
            ->get();

        // Normalize
        $types = ['send', 'receive', 'buy', 'swap', 'withdrawTransaction'];
        $grouped = [];

        foreach ($rawStats as $stat) {
            $month = $stat->month;
            $type = $stat->type;
            $total = $stat->total;

            if (!isset($grouped[$month])) {
                $grouped[$month] = [
                    'month' => $month,
                    'send' => 0,
                    'receive' => 0,
                    'buy' => 0,
                    'swap' => 0,
                    'withdrawTransaction' => 0,
                ];
            }

            $grouped[$month][$type] = $total;
        }

        $graphicalData = array_values($grouped); // Ensure it's an indexed array

        return [
            'transactions' => $transactions,
            'totalTransactions' => $totalTransactions,
            'totalWallets' => $totalWallets,
            'graphicalData' => $graphicalData,
        ];
    }

    public function getTransactionnsForUserWithCurrency($user_id, $currency)
    {
        $totalTransactions = Transaction::where('user_id', $user_id)->where('currency', $currency)->count();
        $totalWallets = VirtualAccount::where('user_id', $user_id)->count();
        $transactions = Transaction::where('user_id', $user_id)->where('currency', $currency)->with('user')->get();
        $transactions = $transactions->map(function ($transaction) {
            $transactionIcon = TransactionIcon::where('type', $transaction->type)->first();
            //just add icon with transaction object
            $transaction->icon = $transactionIcon->icon ?? '';
            return $transaction;
        });
        return ['transactions' => $transactions, 'totalTransactions' => $totalTransactions, 'totalWallets' => $totalWallets];
    }
    public function create(array $data)
    {
        return Transaction::create($data);
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
