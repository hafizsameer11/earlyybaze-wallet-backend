<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class EnableTatumWebhookHmacCommand extends Command
{
    protected $signature = 'tatum:enable-webhook-hmac
                            {--v3 : Also enable HMAC on legacy v3 notifications API}
                            {--clear-cache : Run php artisan optimize:clear after success}';

    protected $description = 'Enable Tatum webhook HMAC using TATUM_WEBHOOK_HMAC_SECRET from env/config';

    public function handle(): int
    {
        $apiKey = trim((string) config('tatum.api_key', ''));
        if ($apiKey === '') {
            $this->error('TATUM_API_KEY is not set.');

            return self::FAILURE;
        }

        $secret = trim((string) config('tatum.webhook_hmac_secret', ''));
        if ($secret === '') {
            $this->error('TATUM_WEBHOOK_HMAC_SECRET is not set.');
            $this->line('Set it in your environment, then run: php artisan optimize:clear');

            return self::FAILURE;
        }

        if (strlen($secret) > 100) {
            $this->error('TATUM_WEBHOOK_HMAC_SECRET must be 100 characters or fewer (Tatum limit).');

            return self::FAILURE;
        }

        $enforce = filter_var(config('tatum.webhook_hmac_enforce', false), FILTER_VALIDATE_BOOLEAN);

        $this->info('Using secret from TATUM_WEBHOOK_HMAC_SECRET (length: '.strlen($secret).')');
        $this->line('TATUM_WEBHOOK_HMAC_ENFORCE='.($enforce ? 'true' : 'false'));
        $this->newLine();

        $v4Base = rtrim((string) config('tatum.v4_base_url', 'https://api.tatum.io/v4'), '/');
        $v3Base = rtrim((string) config('tatum.base_url', 'https://api.tatum.io/v3'), '/');

        $v4Ok = $this->enableOnTatum($apiKey, "{$v4Base}/subscription", 'v4');
        $v3Ok = true;

        if ($this->option('v3')) {
            $v3Ok = $this->enableOnTatum($apiKey, "{$v3Base}/subscription", 'v3');
        }

        if (! $v4Ok || ! $v3Ok) {
            $this->error('Tatum HMAC enable failed on one or more APIs. See output above.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Tatum webhook HMAC enabled successfully.');

        if ($this->option('clear-cache')) {
            $this->info('Clearing caches…');
            Artisan::call('optimize:clear');
        }

        $this->line('Next: php artisan queue:restart');

        return self::SUCCESS;
    }

    private function enableOnTatum(string $apiKey, string $url, string $label): bool
    {
        $secret = trim((string) config('tatum.webhook_hmac_secret', ''));

        $this->line("→ Enabling HMAC on Tatum {$label}: PUT {$url}");

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->put($url, [
                'hmacSecret' => $secret,
            ]);

            if ($response->status() === 204 || $response->successful()) {
                $this->info("  {$label}: OK (HTTP {$response->status()})");

                return true;
            }

            $this->error("  {$label}: failed (HTTP {$response->status()})");
            $body = $response->json();
            $this->line('  '.json_encode($body ?? $response->body()));

            return false;
        } catch (\Throwable $e) {
            $this->error("  {$label}: exception — {$e->getMessage()}");

            return false;
        }
    }
}
