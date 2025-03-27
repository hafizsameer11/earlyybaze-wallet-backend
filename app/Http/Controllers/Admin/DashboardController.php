<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboardData()
    {
        $data = [
            'statsCardData' => $this->statsCardData(),
            'recentTransactions' => $this->recentTransactions()
        ];
        return response()->json($data, 200);
    }
    public function statsCardData()
    {
        $totatUsers = User::count();
        $totalTransactions = Transaction::count();
        $totalWallets = VirtualAccount::count();
        $totalRevenue = Transaction::sum('amount');
        $data = [
            'totalUsers' => $totatUsers,
            'totalTransactions' => $totalTransactions,
            'totalWallets' => $totalWallets,
            'totalRevenue' => $totalRevenue
        ];
        return $data;
    }
    public function recentTransactions()
    {
        $transactions = Transaction::orderBy('created_at', 'desc')->take(5)->get();
        $transactions = $transactions->map(function ($transaction) {
            // $user=User::find($transaction->user_id);
            $currency = WalletCurrency::where('currency', $transaction->currency)->first();
            $symbol = '';
            if ($currency) {
                $symbol = $currency->symbol;
            }
            return [
                'id' => $transaction->id,
                // 'user'=>$user->name,
                'currency' => $transaction->currency,
                'symbol' => $symbol,
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at
            ];
        });
        return $transactions;
    }
}
