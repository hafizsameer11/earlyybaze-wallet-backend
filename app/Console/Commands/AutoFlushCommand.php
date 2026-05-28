<?php

namespace App\Console\Commands;

use App\Services\AutoFlushNotificationService;
use App\Services\SimpleWithdrawalService;
use App\Support\SystemSettingHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoFlushCommand extends Command
{
    protected $signature = 'wallet:auto-flush {--limit=0} {--dry-run}';
    protected $description = 'Flush supported currencies to master destinations automatically';

    public function handle(SimpleWithdrawalService $service, AutoFlushNotificationService $notifier): int
    {
        $enabled = SystemSettingHelper::getBool('auto_flush_enabled', false);
        if (!$enabled) {
            $this->info('Auto flush is disabled. Skipping.');
            return self::SUCCESS;
        }

        $destinations = config('withdrawal_destinations', []);
        if (empty($destinations)) {
            $this->error('No withdrawal destinations configured.');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($destinations as $currency => $destination) {
            $currencyKey = strtoupper((string) $currency);
            try {
                $result = $service->flush($currencyKey, (string) $destination, $limit, $dryRun);
                $result['currency'] = $currencyKey;
                $notifier->sendResult('AUTO FLUSH', $result);
                $this->line(sprintf('%s -> %s', $currencyKey, !empty($result['success']) ? 'ok' : 'failed'));
            } catch (\Throwable $e) {
                Log::error('Auto flush command error', [
                    'currency' => $currencyKey,
                    'error' => $e->getMessage(),
                ]);
                $notifier->sendResult('AUTO FLUSH', [
                    'success' => false,
                    'currency' => $currencyKey,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
