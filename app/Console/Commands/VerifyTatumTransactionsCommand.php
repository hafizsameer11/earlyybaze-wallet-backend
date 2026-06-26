<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class VerifyTatumTransactionsCommand extends Command
{
    protected $signature = 'tatum:verify-tx-samples
                            {--key= : Tatum API key (overrides TATUM_API_KEY / config)}
                            {--v3-only : Only call legacy v3 endpoints}
                            {--v4-only : Only call v4 blockchains API}
                            {--output= : Output JSON path (default: docs/tatum_tx_verification_results.json)}';

    protected $description = 'Fetch real mainnet txs via Tatum v3/v4 and save responses for webhook verification design';

    public function handle(): int
    {
        $apiKey = trim((string) ($this->option('key') ?: config('tatum.api_key', env('TATUM_API_KEY', ''))));
        if ($apiKey === '') {
            $this->error('Missing Tatum API key.');
            $this->line('Set TATUM_API_KEY in .env or pass --key=your-tatum-api-key');
            $this->line('Config: config/tatum.php reads env("TATUM_API_KEY")');

            return self::FAILURE;
        }

        $v3Base = rtrim((string) config('tatum.base_url', 'https://api.tatum.io/v3'), '/');
        $v4Base = rtrim((string) config('tatum.v4_base_url', 'https://api.tatum.io/v4'), '/');
        $v3Only = (bool) $this->option('v3-only');
        $v4Only = (bool) $this->option('v4-only');

        $outputPath = $this->option('output')
            ?: base_path('docs/tatum_tx_verification_results.json');

        $samples = config('tatum_tx_verification_samples.samples', []);
        $runAt = now()->toIso8601String();
        $results = [
            'generated_at' => $runAt,
            'tatum_v3_base' => $v3Base,
            'tatum_v4_base' => $v4Base,
            'purpose' => 'Explore Tatum tx-by-hash responses for webhook + flush on-chain verification',
            'samples' => [],
        ];

        $this->info('Verifying '.count($samples).' sample transactions via Tatum…');

        foreach ($samples as $sample) {
            $id = (string) ($sample['id'] ?? 'unknown');
            $hash = (string) ($sample['tx_hash'] ?? '');
            $this->line("→ {$id} ({$hash})");

            $entry = [
                'meta' => $sample,
                'v3' => null,
                'v4' => null,
            ];

            if (! $v4Only) {
                $entry['v3'] = $this->fetchV3(
                    $apiKey,
                    $v3Base,
                    (string) ($sample['chain_v3'] ?? ''),
                    $hash
                );
            }

            if (! $v3Only) {
                $entry['v4'] = $this->fetchV4(
                    $apiKey,
                    $v4Base,
                    (string) ($sample['chain_v4'] ?? ''),
                    $hash
                );
            }

            $entry['verification_hints'] = $this->buildHints($sample, $entry);
            $results['samples'][$id] = $entry;
        }

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->newLine();
        $this->info("Saved: {$outputPath}");
        $this->summarize($results);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function fetchV3(string $apiKey, string $base, string $chain, string $hash): array
    {
        $url = "{$base}/{$chain}/transaction/{$hash}";

        return $this->httpGet($apiKey, $url, 'v3');
    }

    /** @return array<string, mixed> */
    private function fetchV4(string $apiKey, string $base, string $chain, string $hash): array
    {
        $url = "{$base}/data/blockchains/transaction?".http_build_query([
            'chain' => $chain,
            'hash' => $hash,
        ]);

        return $this->httpGet($apiKey, $url, 'v4');
    }

    /** @return array<string, mixed> */
    private function httpGet(string $apiKey, string $url, string $apiVersion): array
    {
        $started = microtime(true);

        try {
            $response = Http::withHeaders(['x-api-key' => $apiKey])
                ->timeout(45)
                ->get($url);

            $body = $response->json();
            if (! is_array($body)) {
                $body = ['raw' => $response->body()];
            }

            return [
                'api_version' => $apiVersion,
                'url' => $url,
                'http_status' => $response->status(),
                'ok' => $response->successful(),
                'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
                'body' => $body,
                'top_level_keys' => is_array($body) ? array_keys($body) : [],
            ];
        } catch (\Throwable $e) {
            return [
                'api_version' => $apiVersion,
                'url' => $url,
                'http_status' => null,
                'ok' => false,
                'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
                'error' => $e->getMessage(),
                'body' => null,
                'top_level_keys' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $sample
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function buildHints(array $sample, array $entry): array
    {
        $v4Body = is_array($entry['v4']['body'] ?? null) ? $entry['v4']['body'] : [];
        $v3Body = is_array($entry['v3']['body'] ?? null) ? $entry['v3']['body'] : [];

        return [
            'webhook_fields_to_match' => [
                'txId' => $sample['tx_hash'] ?? null,
                'to' => $sample['expected_to'] ?? null,
                'contractAddress' => $sample['contract'] ?? null,
                'value / amount' => 'Compare webhook value with on-chain amount for recipient',
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

    /** @param  array<string, mixed>  $results */
    private function summarize(array $results): void
    {
        foreach ($results['samples'] as $id => $entry) {
            $v3 = $entry['v3']['http_status'] ?? '—';
            $v4 = $entry['v4']['http_status'] ?? '—';
            $v3ok = ! empty($entry['v3']['ok']) ? 'OK' : 'FAIL';
            $v4ok = ! empty($entry['v4']['ok']) ? 'OK' : 'FAIL';
            $this->line(sprintf('  %-12s  v3=%s (%s)  v4=%s (%s)', $id, $v3, $v3ok, $v4, $v4ok));
        }
    }
}
