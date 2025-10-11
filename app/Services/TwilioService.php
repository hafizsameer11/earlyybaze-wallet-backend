<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Exception;

class TwilioService
{
    protected $client;
    protected $from;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->from = config('services.twilio.from');
    }

    /**
     * Send a message (SMS or WhatsApp)
     */
    public function sendVerification(string $phone, string $message, string $type = 'whatsapp'): bool
    {
        try {
            $to = $this->formatNumber($phone, $type);
            $from = $type === 'whatsapp' ? 'whatsapp:' . $this->from : $this->from;

            $this->client->messages->create($to, [
                'from' => $from,
                'body' => $message,
            ]);

            Log::info("✅ Twilio message sent via {$type} to {$to}");
            return true;
        } catch (Exception $e) {
            Log::error('❌ Twilio sendVerification error: ' . $e->getMessage());
            return false;
        }
    }

    private function formatNumber(string $number, string $type)
    {
        if ($type === 'whatsapp' && !str_starts_with($number, 'whatsapp:')) {
            return 'whatsapp:' . $number;
        }
        return preg_match('/^\+/', $number) ? $number : '+' . $number;
    }
}
