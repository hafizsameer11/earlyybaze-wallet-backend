<?php

namespace App\Support;

use App\Models\ExchangeRate;

class FiatExchangeHelper
{
    public static function normalizeFiat(string $fiat): string
    {
        $fiat = strtoupper(trim($fiat));

        return in_array($fiat, ['ZAR', 'RAND'], true) ? 'ZAR' : 'NGN';
    }

    public static function fiatRateRow(string $fiat): ?ExchangeRate
    {
        $fiat = self::normalizeFiat($fiat);

        return ExchangeRate::where('currency', $fiat)->orderByDesc('id')->first();
    }

    /**
     * Convert USD notional to fiat using the anchor fiat row (NGN.rate_naira or ZAR.rate).
     */
    public static function usdToFiat(string $amountUsd, string $fiat): string
    {
        $fiat = self::normalizeFiat($fiat);
        $row = self::fiatRateRow($fiat);

        if (! $row) {
            throw new \Exception("{$fiat} exchange rate not found. Add a {$fiat} row in admin exchange rates.");
        }

        if ($fiat === 'ZAR') {
            $rate = (string) ($row->rate ?? '0');
        } else {
            $rate = (string) ($row->rate_naira ?? $row->rate ?? '0');
        }

        if (bccomp($rate, '0', 8) <= 0) {
            throw new \Exception("Invalid {$fiat} exchange rate.");
        }

        return bcmul($amountUsd, $rate, 8);
    }

    /**
     * Crypto row fiat leg: prefer rate_zar / rate_naira on the asset row, else anchor fiat row.
     */
    public static function usdToFiatViaCryptoRow(string $amountUsd, ExchangeRate $cryptoRow, string $fiat): string
    {
        $fiat = self::normalizeFiat($fiat);

        if ($fiat === 'ZAR' && ! empty($cryptoRow->rate_zar)) {
            return bcmul($amountUsd, (string) $cryptoRow->rate_zar, 8);
        }

        if ($fiat === 'NGN' && ! empty($cryptoRow->rate_naira)) {
            return bcmul($amountUsd, (string) $cryptoRow->rate_naira, 8);
        }

        return self::usdToFiat($amountUsd, $fiat);
    }
}
