<?php

namespace App\Console\Commands;

use App\Services\AiAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RunAiAuditCommand extends Command
{
    protected $signature = 'audit:daily-ai';
    protected $description = 'Run AI-based daily anomaly audit for failed blockchain events';

    public function handle(AiAuditService $service): int
    {
        $result = $service->runDailyAudit();
        Log::info('Daily AI audit result', $result);

        $recipients = array_filter(array_map('trim', explode(',', (string) env('AUTO_FLUSH_EMAILS', ''))));
        if (!empty($recipients)) {
            $body = json_encode($result, JSON_PRETTY_PRINT);
            foreach ($recipients as $to) {
                Mail::raw("Daily AI audit report:\n\n".$body, function ($message) use ($to) {
                    $message->to($to)->subject('Daily AI Audit Report');
                });
            }
        }

        $this->info($result['message'] ?? 'Done');
        return !empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
