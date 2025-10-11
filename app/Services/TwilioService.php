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
  public function sendVerification(string $phone, string $message): bool
{
    try {
        // ✅ Always format as WhatsApp number (with proper country code)
        $to = $this->formatNumber($phone, 'whatsapp');

        // ✅ Always send from the WhatsApp sandbox number
        $from = 'whatsapp:' . $this->from;

        // ✅ Send message via Twilio WhatsApp API
        $this->client->messages->create($to, [
            'from' => $from,
            'body' => $message,
        ]);

        Log::info("✅ WhatsApp message sent successfully to {$to}");
        return true;
    } catch (\Exception $e) {
        Log::error('❌ Twilio WhatsApp sendVerification error: ' . $e->getMessage());
        return false;
    }
}

    private function formatNumber(string $number, string $type)
    {
        $number = preg_replace('/\s+/', '', $number); // remove spaces

        // Already in correct format
        if (str_starts_with($number, '+')) {
            return $type === 'whatsapp' && !str_starts_with($number, 'whatsapp:')
                ? 'whatsapp:' . $number
                : $number;
        }

        // Remove any leading zeros
        $number = ltrim($number, '0');

        // Auto-detect country (Pakistan or Nigeria)
        if (preg_match('/^3\d{8,}$/', $number)) {
            // Starts with 3 (Pakistan pattern)
            $number = '+92' . $number;
        } elseif (preg_match('/^8\d{8,}$/', $number) || preg_match('/^70\d{7,}$/', $number)) {
            // Nigeria MTN/Glo prefixes (simplified)
            $number = '+234' . $number;
        } elseif (!str_starts_with($number, '+')) {
            // Default fallback (Pakistan)
            $number = '+92' . $number;
        }

        // WhatsApp formatting
        if ($type === 'whatsapp' && !str_starts_with($number, 'whatsapp:')) {
            return 'whatsapp:' . $number;
        }

        return $number;
    }
}
