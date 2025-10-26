<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    /**
     * Send notification via Expo Push API (for iOS devices).
     *
     * @param string $expoToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendNotification(string $expoToken, string $title, string $body, array $data = []): array
    {
        $payload = [
            'to' => $expoToken,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];

        try {
            $response = Http::post('https://exp.host/--/api/v2/push/send', $payload);
            $jsonResponse = $response->json();

            Log::info("📨 Expo push notification response", $jsonResponse);

            return $jsonResponse;
        } catch (\Exception $e) {
            Log::error("❌ Failed to send Expo notification: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
