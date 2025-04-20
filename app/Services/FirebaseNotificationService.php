<?php

namespace App\Services;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;

class FirebaseNotificationService
{
    protected $fcmUrl;
    protected $credentialsPath;
    protected $projectId;

    public function __construct()
    {
        $this->fcmUrl = 'https://fcm.googleapis.com/v1/projects/tipsterx-9ff4f/messages:send';
        $this->credentialsPath = storage_path('app/json/earlybaze.json'); // Path to your Service Account key
        $this->projectId = 'earlybazewallet-d6ff3'; // Ensure you set this in your config/services.php
    }

    /**
     * Send a Firebase Cloud Messaging notification.
     *
     * @param string $fcmToken Target device's FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional additional data payload
     * @return array Response from the FCM server
     */
    public function sendNotification($fcmToken, $title, $body, $userId)
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception('Service account JSON file not found at ' . $this->credentialsPath);
        }

        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $accessToken = $client->getAccessToken();

        if (!isset($accessToken['access_token'])) {
            throw new \Exception('Unable to retrieve access token.');
        }

        $headers = [
            "Authorization: Bearer {$accessToken['access_token']}",
            'Content-Type: application/json',
        ];

        $payload = [
            "message" => [
                "token" => $fcmToken,
                "notification" => [ // ✅ This is what displays in system tray
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => [ // ✅ Optional: passed to app for foreground logic
                    "title" => $title,
                    "body" => $body,
                    "userId" => (string) $userId
                ]
            ],
        ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, str_replace('{projectId}', $this->projectId, $this->fcmUrl));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception('Curl Error: ' . $err);
        }

        return json_decode($response, true);
    }
}
