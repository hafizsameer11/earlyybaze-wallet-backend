<?php

namespace App\Support;

/**
 * Extract normalized transfer fields from Tatum v3/v4 transaction bodies.
 */
final class TatumTxResponseParser
{
    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    private const TRANSFER_SELECTOR = 'a9059cbb';

    /**
     * @return list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}>
     */
    public static function extractTransfers(array $body, string $parserType, string $currency): array
    {
        return match ($parserType) {
            'utxo' => self::parseUtxoOutputs($body),
            'evm_native' => self::parseEvmNative($body),
            'evm_token' => self::parseEvmTokenLogs($body, $currency),
            'tron_native' => self::parseTronNative($body),
            'tron_token' => self::parseTronToken($body, $currency),
            default => [],
        };
    }

    public static function isConfirmed(array $body, string $parserType): bool
    {
        if (empty($body)) {
            return false;
        }

        return match ($parserType) {
            'utxo' => isset($body['blockNumber']) && (int) $body['blockNumber'] > 0,
            'evm_native', 'evm_token' => ($body['status'] ?? false) === true
                && isset($body['blockNumber']) && (int) $body['blockNumber'] > 0,
            'tron_native', 'tron_token' => strtoupper((string) ($body['ret'][0]['contractRet'] ?? '')) === 'SUCCESS'
                && isset($body['blockNumber']) && (int) $body['blockNumber'] > 0,
            default => isset($body['blockNumber']),
        };
    }

    /** @return list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}> */
    private static function parseUtxoOutputs(array $body): array
    {
        $transfers = [];
        foreach ($body['outputs'] ?? [] as $i => $out) {
            $addr = $out['address'] ?? null;
            if (! $addr || (int) ($out['value'] ?? 0) <= 0) {
                continue;
            }
            $transfers[] = [
                'from' => null,
                'to' => (string) $addr,
                'amount' => bcdiv((string) $out['value'], '100000000', 8),
                'contract' => null,
                'log_index' => $i,
            ];
        }

        return $transfers;
    }

    /** @return list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}> */
    private static function parseEvmNative(array $body): array
    {
        $to = $body['to'] ?? null;
        $value = $body['value'] ?? '0';
        if (! $to) {
            return [];
        }

        return [[
            'from' => isset($body['from']) ? (string) $body['from'] : null,
            'to' => (string) $to,
            'amount' => bcdiv((string) $value, bcpow('10', '18'), 8),
            'contract' => null,
            'log_index' => isset($body['transactionIndex']) ? (int) $body['transactionIndex'] : null,
        ]];
    }

    /** @return list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}> */
    private static function parseEvmTokenLogs(array $body, string $currency): array
    {
        $expectedContract = TatumChainMapper::expectedContractForCurrency($currency);
        $decimals = TatumChainMapper::tokenDecimals($currency);
        $transfers = [];

        foreach ($body['logs'] ?? [] as $log) {
            $topics = $log['topics'] ?? [];
            if ($topics === []) {
                continue;
            }
            $topic0 = strtolower((string) $topics[0]);
            if ($topic0 !== strtolower(self::TRANSFER_TOPIC)) {
                continue;
            }

            $contract = (string) ($log['address'] ?? '');
            if ($expectedContract !== null && ! AllowedFungibleContracts::addressesEqual($contract, $expectedContract)) {
                continue;
            }

            $from = isset($topics[1]) ? self::decodeEvmAddress($topics[1]) : null;
            $to = isset($topics[2]) ? self::decodeEvmAddress($topics[2]) : null;
            if (! $to) {
                continue;
            }

            $rawAmount = self::hexToDecimal($log['data'] ?? '0x0');
            $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 8);

            $transfers[] = [
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
                'contract' => $contract,
                'log_index' => isset($log['logIndex']) ? (int) $log['logIndex'] : null,
            ];
        }

        return $transfers;
    }

    /** @return list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}> */
    private static function parseTronNative(array $body): array
    {
        $contract = $body['rawData']['contract'][0] ?? null;
        if (! is_array($contract)) {
            return [];
        }

        $value = $contract['parameter']['value'] ?? [];
        $to = $value['toAddressBase58'] ?? null;
        if (! $to) {
            return [];
        }

        $amountSun = (string) ($value['amount'] ?? '0');

        return [[
            'from' => $value['ownerAddressBase58'] ?? null,
            'to' => (string) $to,
            'amount' => bcdiv($amountSun, '1000000', 8),
            'contract' => null,
            'log_index' => null,
        ]];
    }

    /** @return list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}> */
    private static function parseTronToken(array $body, string $currency): array
    {
        $expectedContract = TatumChainMapper::expectedContractForCurrency($currency);
        $decimals = TatumChainMapper::tokenDecimals($currency);
        $transfers = [];

        foreach ($body['log'] ?? [] as $i => $log) {
            $topic0 = strtolower((string) ($log['topics'][0] ?? ''));
            if ($topic0 !== strtolower(ltrim(self::TRANSFER_TOPIC, '0x'))) {
                continue;
            }

            $contractHex = $log['address'] ?? '';
            $contract = self::tronHexToBase58($contractHex) ?? $contractHex;
            if ($expectedContract !== null && ! AllowedFungibleContracts::addressesEqual($contract, $expectedContract)) {
                continue;
            }

            $from = isset($log['topics'][1]) ? self::decodeTronTopicAddress($log['topics'][1]) : null;
            $to = isset($log['topics'][2]) ? self::decodeTronTopicAddress($log['topics'][2]) : null;
            if (! $to) {
                continue;
            }

            $rawAmount = self::hexToDecimal($log['data'] ?? '0');
            $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 8);

            $transfers[] = [
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
                'contract' => $expectedContract ?? $contract,
                'log_index' => $i,
            ];
        }

        if ($transfers !== []) {
            return $transfers;
        }

        $rawContract = $body['rawData']['contract'][0]['parameter']['value'] ?? [];
        $contractBase58 = $rawContract['contractAddressBase58'] ?? null;
        if ($expectedContract !== null && $contractBase58
            && ! AllowedFungibleContracts::addressesEqual($contractBase58, $expectedContract)) {
            return [];
        }

        $data = strtolower(ltrim((string) ($rawContract['data'] ?? ''), '0x'));
        if (str_starts_with($data, self::TRANSFER_SELECTOR) && strlen($data) >= 136) {
            $toHex = substr($data, 8 + 24, 40);
            $amountHex = substr($data, 8 + 64, 64);
            $to = self::tronHexToBase58('41'.$toHex);
            if ($to) {
                $amount = bcdiv(self::hexToDecimal('0x'.$amountHex), bcpow('10', (string) $decimals), 8);
                $transfers[] = [
                    'from' => $rawContract['ownerAddressBase58'] ?? null,
                    'to' => $to,
                    'amount' => $amount,
                    'contract' => $contractBase58 ?? $expectedContract,
                    'log_index' => null,
                ];
            }
        }

        return $transfers;
    }

    private static function decodeEvmAddress(string $topic): ?string
    {
        $hex = strtolower(ltrim($topic, '0x'));
        if (strlen($hex) < 40) {
            return null;
        }

        return '0x'.substr($hex, -40);
    }

    private static function decodeTronTopicAddress(string $topic): ?string
    {
        $hex = strtolower(ltrim($topic, '0x'));
        if (strlen($hex) < 40) {
            return null;
        }

        return self::tronHexToBase58('41'.substr($hex, -40));
    }

    private static function tronHexToBase58(string $hex): ?string
    {
        $hex = ltrim($hex, '0x');
        if ($hex === '' || ! ctype_xdigit($hex)) {
            return null;
        }

        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return null;
        }

        $hash0 = hash('sha256', $bytes, true);
        $hash1 = hash('sha256', $hash0, true);
        $checksum = substr($hash1, 0, 4);
        $data = $bytes.$checksum;

        $num = gmp_init(bin2hex($data), 16);
        $encoded = '';
        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = gmp_div_qr($num, 58);
            $encoded = $alphabet[gmp_intval($rem)].$encoded;
        }

        for ($i = 0, $len = strlen($data); $i < $len && $data[$i] === "\x00"; $i++) {
            $encoded = '1'.$encoded;
        }

        return $encoded !== '' ? $encoded : null;
    }

    private static function hexToDecimal(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '0x');
        if ($hex === '') {
            return '0';
        }

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16));
        }

        return '0';
    }
}
