<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutoFlushNotificationService
{
    public function sendResult(string $subjectPrefix, array $result): void
    {
        $recipients = array_filter(array_map('trim', explode(',', (string) env('AUTO_FLUSH_EMAILS', ''))));
        if (empty($recipients)) {
            Log::warning('Auto flush email skipped: AUTO_FLUSH_EMAILS not configured.');
            return;
        }

        $subject = sprintf(
            '%s [%s] %s',
            $subjectPrefix,
            strtoupper((string)($result['currency'] ?? 'N/A')),
            !empty($result['success']) ? 'SUCCESS' : 'FAILED'
        );

        $body = json_encode($result, JSON_PRETTY_PRINT);
        foreach ($recipients as $to) {
            Mail::raw("Auto flush execution result:\n\n".$body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        }
    }
}
