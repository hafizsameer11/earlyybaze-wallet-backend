<?php

namespace App\Support;

final class TatumChainMapper
{
    /** @return array{v4: string, v3: string, parser: string} */
    public static function forCurrency(string $currency, ?string $blockchain = null): array
    {
        $cur = strtoupper(trim($currency));
        $chain = strtolower(trim((string) $blockchain));

        return match (true) {
            $cur === 'BTC' || str_contains($chain, 'bitcoin') || $chain === 'btc' => [
                'v4' => 'bitcoin-mainnet',
                'v3' => 'bitcoin',
                'parser' => 'utxo',
            ],
            in_array($cur, ['ETH'], true) || ($cur === 'ETH' || (str_contains($chain, 'ethereum') && ! self::isTokenCurrency($cur))) => [
                'v4' => 'ethereum-mainnet',
                'v3' => 'ethereum',
                'parser' => 'evm_native',
            ],
            in_array($cur, ['USDT', 'USDT_ETH', 'USDC', 'USDC_ETH'], true) => [
                'v4' => 'ethereum-mainnet',
                'v3' => 'ethereum',
                'parser' => 'evm_token',
            ],
            in_array($cur, ['BNB', 'BSC'], true) || str_contains($chain, 'bsc') => [
                'v4' => 'bsc-mainnet',
                'v3' => 'bsc',
                'parser' => 'evm_native',
            ],
            in_array($cur, ['USDT_BSC', 'USDC_BSC'], true) => [
                'v4' => 'bsc-mainnet',
                'v3' => 'bsc',
                'parser' => 'evm_token',
            ],
            in_array($cur, ['TRON', 'TRX'], true) || (str_contains($chain, 'tron') && ! self::isTokenCurrency($cur)) => [
                'v4' => 'tron-mainnet',
                'v3' => 'tron',
                'parser' => 'tron_native',
            ],
            in_array($cur, ['USDT_TRON', 'USDC_TRON'], true) => [
                'v4' => 'tron-mainnet',
                'v3' => 'tron',
                'parser' => 'tron_token',
            ],
            str_contains($chain, 'ethereum') => [
                'v4' => 'ethereum-mainnet',
                'v3' => 'ethereum',
                'parser' => self::isTokenCurrency($cur) ? 'evm_token' : 'evm_native',
            ],
            str_contains($chain, 'bsc') => [
                'v4' => 'bsc-mainnet',
                'v3' => 'bsc',
                'parser' => self::isTokenCurrency($cur) ? 'evm_token' : 'evm_native',
            ],
            str_contains($chain, 'tron') => [
                'v4' => 'tron-mainnet',
                'v3' => 'tron',
                'parser' => self::isTokenCurrency($cur) ? 'tron_token' : 'tron_native',
            ],
            default => [
                'v4' => 'ethereum-mainnet',
                'v3' => 'ethereum',
                'parser' => 'evm_native',
            ],
        };
    }

    public static function isTokenCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), [
            'USDT', 'USDC', 'USDT_ETH', 'USDC_ETH',
            'USDT_BSC', 'USDC_BSC', 'USDT_TRON', 'USDC_TRON',
        ], true);
    }

    public static function tokenDecimals(string $currency): int
    {
        return match (strtoupper($currency)) {
            'USDT', 'USDC', 'USDT_ETH', 'USDC_ETH', 'USDT_TRON', 'USDC_TRON' => 6,
            'USDT_BSC', 'USDC_BSC' => 18,
            default => 18,
        };
    }

    public static function expectedContractForCurrency(string $currency): ?string
    {
        return match (strtoupper($currency)) {
            'USDT', 'USDT_ETH' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'USDC', 'USDC_ETH' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'USDT_BSC' => '0x55d398326f99059ff775485246999027b3197955',
            'USDC_BSC' => '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d',
            'USDT_TRON' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'USDC_TRON' => 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8',
            default => null,
        };
    }
}
