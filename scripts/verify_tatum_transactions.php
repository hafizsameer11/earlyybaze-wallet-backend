#!/usr/bin/env php
<?php

/**
 * Standalone runner (no Laravel bootstrap) — useful when .env is broken.
 *
 * Usage:
 *   TATUM_API_KEY=your-key php scripts/verify_tatum_transactions.php
 *   php scripts/verify_tatum_transactions.php --key=your-key
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$samplesFile = $root.'/config/tatum_tx_verification_samples.php';
$outputFile = $root.'/docs/tatum_tx_verification_results.json';

$opts = getopt('', ['key:', 'v3-only', 'v4-only', 'output:']);
$apiKey = trim((string) ($opts['key'] ?? getenv('TATUM_API_KEY') ?: ''));

if ($apiKey === '') {
    fwrite(STDERR, "Missing Tatum API key.\n");
    fwrite(STDERR, "Set TATUM_API_KEY in .env or run: TATUM_API_KEY=xxx php scripts/verify_tatum_transactions.php\n");
    exit(1);
}

if (! file_exists($samplesFile)) {
    fwrite(STDERR, "Samples config not found: {$samplesFile}\n");
    exit(1);
}

$samples = require $samplesFile;
$sampleList = $samples['samples'] ?? [];
$v3Only = array_key_exists('v3-only', $opts);
$v4Only = array_key_exists('v4-only', $opts);
$outputFile = $opts['output'] ?? $outputFile;

$v3Base = rtrim(getenv('TATUM_BASE_URL') ?: 'https://api.tatum.io/v3', '/');
$v4Base = rtrim(getenv('TATUM_V4_BASE_URL') ?: 'https://api.tatum.io/v4', '/');

$results = [
    'generated_at' => gmdate('c'),
    'tatum_v3_base' => $v3Base,
    'tatum_v4_base' => $v4Base,
    'purpose' => 'Explore Tatum tx-by-hash responses for webhook + flush on-chain verification',
    'samples' => [],
];

echo 'Verifying '.count($sampleList)." sample transactions via Tatum…\n";

foreach ($sampleList as $sample) {
    $id = (string) ($sample['id'] ?? 'unknown');
    $hash = (string) ($sample['tx_hash'] ?? '');
    echo "→ {$id}\n";

    $entry = ['meta' => $sample, 'v3' => null, 'v4' => null];

    if (! $v4Only) {
        $chain = (string) ($sample['chain_v3'] ?? '');
        $entry['v3'] = tatumGet($apiKey, "{$v3Base}/{$chain}/transaction/{$hash}", 'v3');
    }

    if (! $v3Only) {
        $chain = (string) ($sample['chain_v4'] ?? '');
        $query = http_build_query(['chain' => $chain, 'hash' => $hash]);
        $entry['v4'] = tatumGet($apiKey, "{$v4Base}/data/blockchains/transaction?{$query}", 'v4');
    }

    $entry['verification_hints'] = buildHints($sample, $entry);
    $results['samples'][$id] = $entry;
}

$dir = dirname($outputFile);
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents($outputFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo "\nSaved: {$outputFile}\n";

foreach ($results['samples'] as $id => $entry) {
    $v3 = $entry['v3']['http_status'] ?? '—';
    $v4 = $entry['v4']['http_status'] ?? '—';
    $v3ok = ! empty($entry['v3']['ok']) ? 'OK' : 'FAIL';
    $v4ok = ! empty($entry['v4']['ok']) ? 'OK' : 'FAIL';
    printf("  %-12s  v3=%s (%s)  v4=%s (%s)\n", $id, $v3, $v3ok, $v4, $v4ok);
}

/** @return array<string, mixed> */
function tatumGet(string $apiKey, string $url, string $apiVersion): array
{
    $started = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}", 'Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $body = json_decode((string) $raw, true);
    if (! is_array($body)) {
        $body = ['raw' => $raw];
    }

    return [
        'api_version' => $apiVersion,
        'url' => $url,
        'http_status' => $status ?: null,
        'ok' => $status >= 200 && $status < 300,
        'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
        'error' => $err !== '' ? $err : null,
        'body' => $body,
        'top_level_keys' => is_array($body) ? array_keys($body) : [],
    ];
}

/** @param array<string, mixed> $sample @param array<string, mixed> $entry @return array<string, mixed> */
function buildHints(array $sample, array $entry): array
{
    $v4Body = is_array($entry['v4']['body'] ?? null) ? $entry['v4']['body'] : [];
    $v3Body = is_array($entry['v3']['body'] ?? null) ? $entry['v3']['body'] : [];

    return [
        'webhook_fields_to_match' => [
            'txId' => $sample['tx_hash'] ?? null,
            'to' => $sample['expected_to'] ?? null,
            'contractAddress' => $sample['contract'] ?? null,
        ],
        'v4_extracted' => [
            'tx_hash' => $v4Body['transactionHash'] ?? $v4Body['hash'] ?? $v4Body['txID'] ?? null,
            'from' => $v4Body['from'] ?? null,
            'to' => $v4Body['to'] ?? null,
            'value' => $v4Body['value'] ?? null,
            'blockNumber' => $v4Body['blockNumber'] ?? null,
            'tron_contractRet' => $v4Body['ret'][0]['contractRet'] ?? null,
        ],
        'v3_extracted' => [
            'tx_hash' => $v3Body['transactionHash'] ?? $v3Body['hash'] ?? $v3Body['txID'] ?? null,
            'from' => $v3Body['from'] ?? null,
            'to' => $v3Body['to'] ?? null,
            'value' => $v3Body['value'] ?? null,
            'blockNumber' => $v3Body['blockNumber'] ?? null,
            'status' => $v3Body['status'] ?? null,
        ],
        'notes' => $sample['notes'] ?? null,
    ];
}
