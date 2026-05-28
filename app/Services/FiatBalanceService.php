<?php

namespace App\Services;

use App\Models\UserAccount;
use Exception;

class FiatBalanceService
{
    public static function normalizeCurrency(array $data): string
    {
        $raw = strtoupper(trim((string) ($data['currency'] ?? $data['asset'] ?? 'NGN')));

        return match ($raw) {
            'ZAR', 'RAND' => 'ZAR',
            'NGN', 'NAIRA' => 'NGN',
            default => in_array($raw, ['NGN', 'ZAR'], true) ? $raw : 'NGN',
        };
    }

    public static function assetLabel(string $currency): string
    {
        return $currency === 'ZAR' ? 'zar' : 'naira';
    }

    public static function withdrawFeeType(string $currency): string
    {
        return $currency === 'ZAR' ? 'withdraw_rand' : 'withdraw';
    }

    /** Fiat stored on user_accounts — never as crypto virtual accounts. */
    public static function isLedgerFiat(string $code): bool
    {
        $code = strtoupper(trim($code));

        return in_array($code, ['NGN', 'NAIRA', 'ZAR', 'RAND'], true);
    }

    public function getAvailableBalance(int $userId, string $currency): string
    {
        $userAccount = UserAccount::where('user_id', $userId)->first();

        if ($currency === 'ZAR') {
            return (string) ($userAccount->zar_balance ?? '0');
        }

        return (string) ($userAccount->naira_balance ?? '0');
    }

    public function deduct(int $userId, string $currency, string $total): string
    {
        $userAccount = UserAccount::where('user_id', $userId)->lockForUpdate()->first();

        if (! $userAccount) {
            throw new Exception('User Account not found');
        }

        if ($currency === 'ZAR') {
            $before = (string) ($userAccount->zar_balance ?? '0');
            if (bccomp($before, $total, 8) < 0) {
                throw new Exception('Insufficient Balance');
            }
            $userAccount->zar_balance = bcsub($before, $total, 8);
            $userAccount->save();

            return $before;
        }

        $before = (string) $userAccount->naira_balance;
        if (bccomp($before, $total, 8) < 0) {
            throw new Exception('Insufficient Balance');
        }

        $userAccount->naira_balance = bcsub($before, $total, 8);
        $userAccount->save();

        return $before;
    }

    public function credit(int $userId, string $currency, string $amount): void
    {
        $userAccount = UserAccount::where('user_id', $userId)->lockForUpdate()->first();

        if (! $userAccount) {
            $userAccount = UserAccount::create([
                'user_id' => $userId,
                'naira_balance' => '0',
                'zar_balance' => '0',
                'crypto_balance' => '0',
            ]);
        }

        if ($currency === 'ZAR') {
            $userAccount->zar_balance = bcadd((string) ($userAccount->zar_balance ?? '0'), $amount, 8);
            $userAccount->save();

            return;
        }

        $userAccount->naira_balance = bcadd((string) $userAccount->naira_balance, $amount, 8);
        $userAccount->save();
    }
}
