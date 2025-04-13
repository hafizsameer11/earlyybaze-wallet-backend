<?php

namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WalletManagementRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }

    public function create(array $data)
    {
        // Add logic to create data
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function getVirtualWalletsData()
    {
        $users = User::whereHas("virtualAccounts")
            ->with('virtualAccounts.walletCurrency')
            ->orderBy("id", "desc")
            ->get();

        // Aggregate wallet data
        $totalWallets = $users->sum(fn($user) => $user->virtualAccounts->count()); // Total wallets across all users
        $activeWallets = $users->sum(fn($user) => $user->virtualAccounts->where('active', 1)->count()); // Count only active wallets
        $inactiveWallets = $totalWallets - $activeWallets; // Remaining wallets are inactive

        // Cards Data
        $cardsData = [
            [
                "icon" => asset("icons/Wallet.png"),
                "iconBg" => "bg-[#CA1919]",
                "heading" => "total",
                "subheading" => "wallets",
                "cardValue" => $totalWallets,
                "valueStatus" => true
            ],
            [
                "icon" => asset("icons/Wallet.png"),
                "iconBg" => "bg-[#CA1919]",
                "heading" => "active",
                "subheading" => "wallets",
                "cardValue" => $activeWallets,
                "valueStatus" => true
            ],
            [
                "icon" => asset("icons/Wallet.png"),
                "iconBg" => "bg-[#CA1919]",
                "heading" => "inactive",
                "subheading" => "wallets",
                "cardValue" => $inactiveWallets,
                "valueStatus" => true
            ]
        ];

        function getExchangeRate($currency)
        {
            return ExchangeRate::where('currency', $currency)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // Table Data
        $tableData = $users->map(function ($user) {
            $totalFundsUsd = "0";

            foreach ($user->virtualAccounts as $account) {
                $exchangeRate = ExchangeRate::where('currency', $account->currency)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $balance = (string) $account->available_balance;
                $rate = (string) optional($exchangeRate)->rate_usd;
                Log::info("Exchange Rate", ["currency" => $account->currency, "rate" => $rate]);
                Log::info("Account Balance", ["balance" => $balance]);
                if (is_numeric($balance) && is_numeric($rate)) {
                    $amountUsd = bcmul($balance, $rate, 8);
                    $totalFundsUsd = bcadd($totalFundsUsd, $amountUsd, 8);
                }
            }

            return [
                "id" => $user->id,
                "name" => $user->name,
                "profileimg" => "/storage/" . $user->profile_picture,
                "walletCount" => $user->virtualAccounts->count(),
                "totalFunds" => number_format((float) $totalFundsUsd, 2),
                "mostActive" => optional($user->virtualAccounts->sortByDesc('available_balance')->first())->currency ?? "N/A",
                "status" => $user->is_active ? "online" : "offline",
            ];
        });
        return [
            "cardsData" => $cardsData,
            "tableData" => $tableData
        ];
    }
}
