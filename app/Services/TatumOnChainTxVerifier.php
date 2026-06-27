<?php

namespace App\Services;

use App\DTO\OnChainVerificationResult;
use App\Models\DepositAddress;
use App\Models\VirtualAccount;
use App\Support\AllowedFungibleContracts;
use App\Support\TatumChainMapper;
use App\Support\TatumTxResponseParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TatumOnChainTxVerifier
{
    public function fetchTransaction(string $currency, string $txHash, ?string $blockchain = null): ?array
    {
        $txHash = trim($txHash);
        if ($txHash === '') {
            return null;
        }

        $map = TatumChainMapper::forCurrency($currency, $blockchain);
        $apiKey = config('tatum.api_key');
        $v4Base = rtrim((string) config('tatum.v4_base_url', 'https://api.tatum.io/v4'), '/');
        $v3Base = rtrim((string) config('tatum.base_url', 'https://api.tatum.io/v3'), '/');

        $v4Url = $v4Base.'/data/blockchains/transaction?'.http_build_query([
            'chain' => $map['v4'],
            'hash' => $txHash,
        ]);

        try {
            $v4 = Http::withHeaders(['x-api-key' => $apiKey])->timeout(45)->get($v4Url);
            if ($v4->successful()) {
                $body = $v4->json();
                if (is_array($body) && ($body['hash'] ?? $body['transactionHash'] ?? $body['txID'] ?? null)) {
                    return $body;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Tatum v4 tx fetch failed', ['hash' => $txHash, 'error' => $e->getMessage()]);
        }

        $v3Url = $v3Base.'/'.$map['v3'].'/transaction/'.$txHash;
        try {
            $v3 = Http::withHeaders(['x-api-key' => $apiKey])->timeout(45)->get($v3Url);
            if ($v3->successful()) {
                $body = $v3->json();
                if (is_array($body)) {
                    return $body;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Tatum v3 tx fetch failed', ['hash' => $txHash, 'error' => $e->getMessage()]);
        }

        return null;
    }

    public function verifyDeposit(
        array $webhookPayload,
        VirtualAccount $account,
        DepositAddress $deposit,
        ?array $prefetchedBody = null,
    ): OnChainVerificationResult {
        $txId = (string) ($webhookPayload['txId'] ?? '');
        $currency = strtoupper((string) $account->currency);
        $expectedTo = (string) ($webhookPayload['to'] ?? $webhookPayload['address'] ?? $deposit->address);
        $expectedAmount = (string) ($webhookPayload['value'] ?? $webhookPayload['amount'] ?? '0');
        $expectedLogIndex = AllowedFungibleContracts::payloadLogIndex($webhookPayload);
        $expectedContract = AllowedFungibleContracts::payloadContract($webhookPayload);

        return $this->verifyAgainstExpectations(
            currency: $currency,
            txHash: $txId,
            expectedTo: $expectedTo,
            expectedAmount: $expectedAmount,
            expectedFrom: null,
            blockchain: $account->blockchain,
            expectedLogIndex: $expectedLogIndex,
            expectedContract: $expectedContract !== '' ? $expectedContract : null,
            prefetchedBody: $prefetchedBody,
        );
    }

    public function verifyFlush(
        string $currency,
        string $txHash,
        ?string $expectedFrom,
        string $expectedTo,
        string $expectedAmount,
        ?string $blockchain = null,
        ?array $prefetchedBody = null,
    ): OnChainVerificationResult {
        $expectedContract = TatumChainMapper::isTokenCurrency($currency)
            ? TatumChainMapper::expectedContractForCurrency($currency)
            : null;

        return $this->verifyAgainstExpectations(
            currency: strtoupper($currency),
            txHash: $txHash,
            expectedTo: $expectedTo,
            expectedAmount: $expectedAmount,
            expectedFrom: $expectedFrom,
            blockchain: $blockchain,
            expectedLogIndex: null,
            expectedContract: $expectedContract,
            prefetchedBody: $prefetchedBody,
        );
    }

    public function parseBody(array $body, string $currency, ?string $blockchain = null): OnChainVerificationResult
    {
        $map = TatumChainMapper::forCurrency($currency, $blockchain);
        $confirmed = TatumTxResponseParser::isConfirmed($body, $map['parser']);
        $transfers = TatumTxResponseParser::extractTransfers($body, $map['parser'], strtoupper($currency));
        $first = $transfers[0] ?? null;

        return new OnChainVerificationResult(
            found: true,
            confirmed: $confirmed,
            matches: false,
            from: $first['from'] ?? null,
            to: $first['to'] ?? null,
            amount: $first['amount'] ?? null,
            contract: $first['contract'] ?? null,
            logIndex: $first['log_index'] ?? null,
            blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
            raw: $body,
        );
    }

    private function verifyAgainstExpectations(
        string $currency,
        string $txHash,
        string $expectedTo,
        string $expectedAmount,
        ?string $expectedFrom,
        ?string $blockchain,
        ?int $expectedLogIndex,
        ?string $expectedContract,
        ?array $prefetchedBody,
    ): OnChainVerificationResult {
        $body = $prefetchedBody ?? $this->fetchTransaction($currency, $txHash, $blockchain);
        if ($body === null) {
            return OnChainVerificationResult::notFound();
        }

        $map = TatumChainMapper::forCurrency($currency, $blockchain);
        if (! TatumTxResponseParser::isConfirmed($body, $map['parser'])) {
            return new OnChainVerificationResult(
                found: true,
                confirmed: false,
                matches: false,
                blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
                failureCode: OnChainVerificationResult::FAIL_TX_UNCONFIRMED,
                failureMessage: 'Transaction found but awaiting on-chain confirmation',
                raw: $body,
            );
        }

        $transfers = TatumTxResponseParser::extractTransfers($body, $map['parser'], $currency);
        if ($transfers === []) {
            return new OnChainVerificationResult(
                found: true,
                confirmed: true,
                matches: false,
                blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
                failureCode: OnChainVerificationResult::FAIL_TX_NOT_FOUND,
                failureMessage: 'No matching transfer outputs in transaction',
                raw: $body,
            );
        }

        $matchingTransfers = [];
        foreach ($transfers as $transfer) {
            if (! AllowedFungibleContracts::addressesEqual($transfer['to'], $expectedTo)) {
                continue;
            }
            if ($expectedLogIndex !== null && $transfer['log_index'] !== null
                && (int) $transfer['log_index'] !== $expectedLogIndex) {
                continue;
            }
            if ($expectedContract !== null && ($transfer['contract'] ?? null)
                && ! AllowedFungibleContracts::addressesEqual($transfer['contract'], $expectedContract)) {
                continue;
            }
            $matchingTransfers[] = $transfer;
        }

        if ($matchingTransfers === []) {
            return new OnChainVerificationResult(
                found: true,
                confirmed: true,
                matches: false,
                blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
                failureCode: OnChainVerificationResult::FAIL_ADDRESS_MISMATCH,
                failureMessage: 'On-chain recipient does not match expected deposit address',
                raw: $body,
            );
        }

        $match = $matchingTransfers[0];
        $onChainAmount = $this->resolveOnChainAmount($map['parser'], $expectedLogIndex, $matchingTransfers);

        if ($expectedFrom !== null && ($match['from'] ?? null)
            && ! AllowedFungibleContracts::addressesEqual($match['from'], $expectedFrom)) {
            return new OnChainVerificationResult(
                found: true,
                confirmed: true,
                matches: false,
                from: $match['from'],
                to: $match['to'],
                amount: $match['amount'],
                contract: $match['contract'] ?? null,
                logIndex: $match['log_index'] ?? null,
                blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
                failureCode: OnChainVerificationResult::FAIL_ADDRESS_MISMATCH,
                failureMessage: 'On-chain sender does not match expected address',
                raw: $body,
            );
        }

        if ($expectedContract !== null && ($match['contract'] ?? null)
            && ! AllowedFungibleContracts::addressesEqual($match['contract'], $expectedContract)) {
            return new OnChainVerificationResult(
                found: true,
                confirmed: true,
                matches: false,
                from: $match['from'],
                to: $match['to'],
                amount: $match['amount'],
                contract: $match['contract'],
                blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
                failureCode: OnChainVerificationResult::FAIL_CONTRACT_MISMATCH,
                failureMessage: 'Token contract on chain does not match expected contract',
                raw: $body,
            );
        }

        if (! $this->flushAmountsMatch($expectedAmount, $onChainAmount, $map['parser'], $expectedLogIndex, $body)) {
            return new OnChainVerificationResult(
                found: true,
                confirmed: true,
                matches: false,
                from: $match['from'],
                to: $match['to'],
                amount: $onChainAmount,
                contract: $match['contract'] ?? null,
                logIndex: $match['log_index'] ?? null,
                blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
                failureCode: OnChainVerificationResult::FAIL_AMOUNT_MISMATCH,
                failureMessage: 'On-chain amount does not match expected amount',
                raw: $body,
            );
        }

        return new OnChainVerificationResult(
            found: true,
            confirmed: true,
            matches: true,
            from: $match['from'],
            to: $match['to'],
            amount: $onChainAmount,
            contract: $match['contract'] ?? null,
            logIndex: $match['log_index'] ?? null,
            blockNumber: isset($body['blockNumber']) ? (int) $body['blockNumber'] : null,
            raw: $body,
        );
    }

    /**
     * BTC/LTC batch flushes may split value across multiple UTXO outputs to the same address.
     *
     * @param  list<array{from: ?string, to: string, amount: string, contract: ?string, log_index: ?int}>  $matchingTransfers
     */
    private function resolveOnChainAmount(string $parserType, ?int $expectedLogIndex, array $matchingTransfers): string
    {
        if ($parserType !== 'utxo' || $expectedLogIndex !== null || count($matchingTransfers) === 1) {
            return (string) $matchingTransfers[0]['amount'];
        }

        return $this->sumAmounts(array_column($matchingTransfers, 'amount'));
    }

    /** @param  list<string>  $amounts */
    private function sumAmounts(array $amounts): string
    {
        if ($amounts === []) {
            return '0';
        }

        if (function_exists('bcadd')) {
            $sum = '0';
            foreach ($amounts as $amount) {
                $sum = bcadd($sum, trim((string) $amount), 8);
            }

            return $sum;
        }

        return number_format(array_sum(array_map('floatval', $amounts)), 8, '.', '');
    }

    /**
     * Compare expected flush amount to on-chain value.
     *
     * UTXO batch flushes: DB rows sum to total inputs; on-chain outputs = inputs - fee
     * (platform cut may appear as a second output to the same master address).
     */
    private function flushAmountsMatch(
        string $expectedAmount,
        string $onChainAmount,
        string $parserType,
        ?int $expectedLogIndex,
        array $body,
    ): bool {
        if ($this->amountsMatch($expectedAmount, $onChainAmount)) {
            return true;
        }

        if ($parserType !== 'utxo' || $expectedLogIndex !== null || ! isset($body['fee'])) {
            return false;
        }

        $feeBtc = bcdiv((string) $body['fee'], '100000000', 8);

        return $this->amountsMatch($expectedAmount, bcadd($onChainAmount, $feeBtc, 8));
    }

    private function amountsMatch(string $expected, string $actual): bool
    {
        $expected = trim($expected);
        $actual = trim($actual);
        if ($expected === '' || $actual === '') {
            return false;
        }

        if (function_exists('bccomp')) {
            return bccomp($expected, $actual, 8) === 0;
        }

        return abs((float) $expected - (float) $actual) < 0.00000001;
    }
}
