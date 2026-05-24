<?php

namespace App\Support;

use App\Models\VirtualAccount;

/**
 * Allowlisted ERC-20 / TRC-20 / BEP-20 contracts for deposit webhooks.
 * Fungible incoming events must match one of these before crediting a user.
 */
final class AllowedFungibleContracts
{
    private const TRON_USDT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private const TRON_USDC = 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8';

    private const ETH_USDT = '0xdac17f958d2ee523a2206206994597c13d831ec7';

    private const ETH_USDC = '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48';

    private const BSC_USDT = '0x55d398326f99059ff775485246999027b3197955';

    private const BSC_USDC = '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d';

    public const REJECT_NON_ALLOWLISTED_CONTRACT = 'non_allowlisted_contract';

    public const REJECT_FUNGIBLE_ON_NATIVE_WALLET = 'fungible_on_native_wallet';

    public const REJECT_CONTRACT_WALLET_MISMATCH = 'contract_wallet_mismatch';

    public const REJECT_MISSING_CONTRACT = 'missing_contract_address';

    /** Native coin wallets must never be credited from fungible/token webhooks. */
    private const NATIVE_WALLET_CURRENCIES = [
        'ETH', 'BNB', 'BSC', 'BTC', 'TRON', 'TRX', 'LTC', 'MATIC', 'POLYGON',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::TRON_USDT,
            self::TRON_USDC,
            self::ETH_USDT,
            self::ETH_USDC,
            self::BSC_USDT,
            self::BSC_USDC,
        ];
    }

    public static function isAllowed(?string $contract): bool
    {
        $contract = trim((string) $contract);
        if ($contract === '') {
            return false;
        }

        foreach (self::all() as $allowed) {
            if (self::addressesEqual($allowed, $contract)) {
                return true;
            }
        }

        return false;
    }

    public static function matchesVirtualAccount(VirtualAccount $va, ?string $contract): bool
    {
        $contract = trim((string) $contract);
        if ($contract === '') {
            return false;
        }

        $wc = $va->walletCurrency;
        $ours = trim((string) ($wc->contract_address ?? ''));
        $vaCur = strtoupper((string) $va->currency);

        if ($ours !== '' && self::addressesEqual($ours, $contract)) {
            return true;
        }

        return match ($vaCur) {
            'USDT_TRON' => self::addressesEqual($contract, self::TRON_USDT),
            'USDC_TRON' => self::addressesEqual($contract, self::TRON_USDC),
            'USDT', 'USDT_ETH' => self::addressesEqual($contract, self::ETH_USDT),
            'USDC', 'USDC_ETH' => self::addressesEqual($contract, self::ETH_USDC),
            'USDT_BSC' => self::addressesEqual($contract, self::BSC_USDT),
            'USDC_BSC' => self::addressesEqual($contract, self::BSC_USDC),
            default => false,
        };
    }

    public static function isFungiblePayload(array $data): bool
    {
        $subType = strtoupper((string) ($data['subscriptionType'] ?? ''));
        if ($subType === 'INCOMING_FUNGIBLE_TX') {
            return true;
        }

        $meta = is_array($data['tokenMetadata'] ?? null) ? $data['tokenMetadata'] : [];
        $metaType = strtolower(trim((string) ($meta['type'] ?? '')));
        $kind = strtolower(trim((string) ($data['kind'] ?? '')));

        return $metaType === 'fungible'
            || $kind === 'token_transfer'
            || trim((string) ($data['contractAddress'] ?? $data['contract'] ?? '')) !== '';
    }

    public static function isNativeWallet(VirtualAccount $va): bool
    {
        $wc = $va->walletCurrency;
        if ($wc && ($wc->is_token ?? false)) {
            return false;
        }

        return in_array(strtoupper((string) $va->currency), self::NATIVE_WALLET_CURRENCIES, true);
    }

    /**
     * Return a rejection reason code, or null when the fungible deposit is valid.
     */
    public static function rejectReasonForFungibleDeposit(VirtualAccount $va, array $data): ?string
    {
        if (! self::isFungiblePayload($data)) {
            return null;
        }

        if (self::isNativeWallet($va)) {
            return self::REJECT_FUNGIBLE_ON_NATIVE_WALLET;
        }

        $contract = self::payloadContract($data);
        if ($contract === '') {
            return self::REJECT_MISSING_CONTRACT;
        }

        if (! self::isAllowed($contract)) {
            return self::REJECT_NON_ALLOWLISTED_CONTRACT;
        }

        if (! self::matchesVirtualAccount($va, $contract)) {
            return self::REJECT_CONTRACT_WALLET_MISMATCH;
        }

        return null;
    }

    public static function rejectionReasonLabel(string $code): string
    {
        return match ($code) {
            self::REJECT_NON_ALLOWLISTED_CONTRACT => 'Fake or unsupported token contract (not USDT/USDC allowlist)',
            self::REJECT_FUNGIBLE_ON_NATIVE_WALLET => 'Token transfer cannot credit native wallet (e.g. fake ETH token)',
            self::REJECT_CONTRACT_WALLET_MISMATCH => 'Token contract does not match user wallet currency',
            self::REJECT_MISSING_CONTRACT => 'Fungible webhook missing contract address',
            default => $code,
        };
    }

    public static function payloadContract(array $data): string
    {
        return trim((string) ($data['contractAddress'] ?? $data['contract'] ?? ''));
    }

    public static function payloadLogIndex(array $data): ?int
    {
        if (array_key_exists('logIndex', $data) && $data['logIndex'] !== null && $data['logIndex'] !== '') {
            return (int) $data['logIndex'];
        }
        if (array_key_exists('index', $data) && $data['index'] !== null && $data['index'] !== '') {
            return (int) $data['index'];
        }

        return null;
    }

    public static function addressesEqual(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_starts_with(strtolower($a), '0x') && str_starts_with(strtolower($b), '0x')) {
            return strtolower($a) === strtolower($b);
        }

        return strcasecmp($a, $b) === 0;
    }
}
