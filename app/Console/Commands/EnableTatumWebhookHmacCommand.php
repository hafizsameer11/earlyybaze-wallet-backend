<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class EnableTatumWebhookHmacCommand extends Command
{
    protected $signature = 'tatum:enable-webhook-hmac
                            {--secret= : Secret to use for x-payload-hash (if empty, one will be generated)}
                            {--enforce=0 : When 1, rejects invalid signatures (otherwise monitor/log)}
                            {--write-env=0 : When 1, updates .env with the secret and enforce flag}
                            {--env-path= : Path to .env (default: base_path(".env"))}
                            {--clear-cache=0 : When 1, runs php artisan optimize:clear}';

    protected $description = 'Enable Tatum webhook HMAC verification (x-payload-hash) via env/config';

    public function handle(): int
    {
        $envPath = (string) ($this->option('env-path') ?: base_path('.env'));
        $writeEnv = (bool) $this->option('write-env');
        $enforce = (bool) $this->option('enforce');
        $clearCache = (bool) $this->option('clear-cache');

        $secret = (string) $this->option('secret');
        if (trim($secret) === '') {
            // 48 bytes -> 64 chars base64-ish length; Tatum doc says secret length <= 100.
            $secret = base64_encode(random_bytes(48));
        }

        $secret = trim($secret);
        if ($secret === '') {
            $this->error('HMAC secret is empty.');
            return self::FAILURE;
        }

        $this->line('Tatum webhook HMAC settings:');
        $this->line('  TATUM_WEBHOOK_HMAC_SECRET=' . $secret);
        $this->line('  TATUM_WEBHOOK_HMAC_ENFORCE=' . ($enforce ? 'true' : 'false'));
        $this->newLine();

        if (! $writeEnv) {
            $this->line('Run with --write-env=1 to update your .env automatically.');
            $this->line('After updating .env, you should run: php artisan optimize:clear && php artisan queue:restart');
            return self::SUCCESS;
        }

        if (! is_file($envPath)) {
            $this->error('Env file not found at: ' . $envPath);
            return self::FAILURE;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            $this->error('Failed reading env file at: ' . $envPath);
            return self::FAILURE;
        }

        $content = $this->upsertEnvVar($content, 'TATUM_WEBHOOK_HMAC_SECRET', $secret);
        $content = $this->upsertEnvVar($content, 'TATUM_WEBHOOK_HMAC_ENFORCE', $enforce ? 'true' : 'false');

        $ok = file_put_contents($envPath, $content);
        if ($ok === false) {
            $this->error('Failed writing env file at: ' . $envPath);
            return self::FAILURE;
        }

        $this->info('Updated: ' . $envPath);

        if ($clearCache) {
            $this->info('Clearing caches (php artisan optimize:clear)…');
            Artisan::call('optimize:clear');
        }

        $this->line('Next: php artisan queue:restart');
        return self::SUCCESS;
    }

    private function upsertEnvVar(string $envContent, string $key, string $value): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $envContent);
        if ($lines === false) {
            return $envContent;
        }

        $replaced = false;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
                $lines[$i] = $key . '=' . $value;
                $replaced = true;
            }
        }

        if (! $replaced) {
            $lines[] = '';
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines) . "\n";
    }
}

