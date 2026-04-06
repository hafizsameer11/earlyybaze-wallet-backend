<?php

namespace App\Support;

use App\Models\WalletCurrency;

class WalletFlowV2
{
    public static function currencyAllowedForV2(WalletCurrency $wc): bool
    {
        $chainKey = self::resolveChainKey((string) $wc->blockchain);
        $allowedKeys = config('tatum_v2.v2_allowed_chain_keys', []);
        if (! $chainKey || ! in_array($chainKey, $allowedKeys, true)) {
            return false;
        }

        $currency = strtoupper(trim((string) $wc->currency));
        $isToken = (bool) ($wc->is_token ?? false);

        if ($isToken) {
            if ($chainKey === 'bitcoin') {
                return false;
            }

            return self::isV2StablecoinCurrency($currency);
        }

        $natives = config('tatum_v2.v2_native_currencies_by_chain.'.$chainKey, []);

        return in_array($currency, array_map('strtoupper', $natives), true);
    }

    public static function isV2StablecoinCurrency(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        $list = config('tatum_v2.v2_stablecoin_currencies', []);

        return in_array($currency, array_map('strtoupper', $list), true)
            || $currency === 'USDT'
            || $currency === 'USDC';
    }

    public static function tatumBlockchainUpper(string $chainKey): string
    {
        return match ($chainKey) {
            'bitcoin' => 'BITCOIN',
            'ethereum' => 'ETHEREUM',
            'bsc' => 'BSC',
            'tron' => 'TRON',
            default => throw new \InvalidArgumentException('Unsupported v2 chain key: '.$chainKey),
        };
    }

    public static function resolveChainKey(string $walletCurrencyBlockchain): ?string
    {
        $b = strtolower(trim($walletCurrencyBlockchain));
        $aliases = config('tatum_v2.blockchain_aliases', []);
        if (isset($aliases[$b])) {
            $b = $aliases[$b];
        }

        $profiles = config('tatum_v2.chain_profiles', []);
        if (array_key_exists($b, $profiles)) {
            return $b;
        }

        foreach (config('tatum_v2.shared_address_groups', []) as $groupKey => $members) {
            if (in_array($b, $members, true)) {
                return $groupKey;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function chainProfile(string $chainKey): ?array
    {
        return config('tatum_v2.chain_profiles.'.$chainKey);
    }

    public static function v4ChainForProfile(array $profile): string
    {
        $type = config('tatum_v2.v4_network_type', 'mainnet');

        return $type === 'testnet'
            ? ($profile['v4_chain_testnet'] ?? $profile['v4_chain'])
            : $profile['v4_chain'];
    }

    public static function syntheticAccountId(int $userId, int $currencyId): string
    {
        return 'v2-u'.$userId.'-wc'.$currencyId.'-'.bin2hex(random_bytes(6));
    }
}
