<?php

namespace App\Repositories;

use App\Models\User;

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

        // Table Data
        $tableData = $users->map(function ($user) {
            return [
                "id" => $user->id,
                "name" => $user->name,
                "walletCount" => $user->virtualAccounts->count(),
                "totalFunds" => number_format($user->virtualAccounts->sum('account_balance'), 2),
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
