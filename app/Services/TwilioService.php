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
    $number = ltrim($number, '0'); // remove leading zeros

    // ✅ Always use Nigerian prefix (+234)
    $formatted = '+234' . $number;

    // ✅ Add WhatsApp format if needed
    if ($type === 'whatsapp' && !str_starts_with($formatted, 'whatsapp:')) {
        $formatted = 'whatsapp:' . $formatted;
    }

    return $formatted;
}

}
