<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SentDmService
{
    private ?array $resolvedTemplate = null;

    public function listTemplates(): array
    {
        $apiKey = config('services.sentdm.api_key');
        if (empty($apiKey)) {
            return [];
        }

        return $this->fetchTemplates($apiKey);
    }

    public function sendWhatsAppVerification(string $phone, string $code): bool
    {
        $apiKey = config('services.sentdm.api_key');
        if (empty($apiKey)) {
            Log::error('Sent.dm is not configured (missing SENT_DM_API_KEY)');

            return false;
        }

        $template = $this->resolveTemplate($apiKey);
        if ($template === null) {
            Log::error('Sent.dm OTP template not found. Set SENT_DM_WHATSAPP_TEMPLATE_ID or verify your Sent account has pre-built OTP templates.');

            return false;
        }

        $channels = config('services.sentdm.channels', ['whatsapp']);
        $to = $this->formatE164($phone);

        $payload = [
            'to' => [$to],
            'channel' => $channels,
            'template' => [
                'id' => $template['id'],
                'parameters' => [
                    $template['otp_parameter'] => $code,
                ],
            ],
        ];

        if (config('services.sentdm.sandbox')) {
            $payload['sandbox'] = true;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post(
                rtrim(config('services.sentdm.base_url'), '/').'/v3/messages',
                $payload
            );

            if (! $response->successful()) {
                Log::error('Sent.dm WhatsApp verification failed', [
                    'phone' => $to,
                    'template_id' => $template['id'],
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }

            $body = $response->json();
            if (($body['success'] ?? false) !== true) {
                Log::error('Sent.dm WhatsApp verification rejected', [
                    'phone' => $to,
                    'body' => $body,
                ]);

                return false;
            }

            Log::info('Sent.dm verification queued', [
                'phone' => $to,
                'template_id' => $template['id'],
                'channels' => $channels,
                'request_id' => $body['meta']['request_id'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Sent.dm verification error: '.$e->getMessage(), [
                'phone' => $to,
            ]);

            return false;
        }
    }

    /**
     * @return array{id: string, otp_parameter: string, name: string}|null
     */
    private function resolveTemplate(string $apiKey): ?array
    {
        if ($this->resolvedTemplate !== null) {
            return $this->resolvedTemplate;
        }

        $configuredId = config('services.sentdm.whatsapp_template_id');
        if (! empty($configuredId)) {
            $parameter = config('services.sentdm.otp_parameter', 'code');

            $this->resolvedTemplate = [
                'id' => $configuredId,
                'otp_parameter' => $parameter,
                'name' => 'configured',
            ];

            return $this->resolvedTemplate;
        }

        $templates = $this->fetchTemplates($apiKey);
        $picked = $this->pickOtpTemplate($templates);

        if ($picked === null) {
            return null;
        }

        Log::info('Sent.dm auto-selected OTP template', $picked);
        $this->resolvedTemplate = $picked;

        return $this->resolvedTemplate;
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array{id: string, otp_parameter: string, name: string}|null
     */
    private function pickOtpTemplate(array $templates): ?array
    {
        $preferredName = strtolower((string) config('services.sentdm.template_name', ''));
        $channels = config('services.sentdm.channels', ['whatsapp']);

        $candidates = [];

        foreach ($templates as $template) {
            $status = strtoupper((string) ($template['status'] ?? ''));
            if (! in_array($status, ['APPROVED', 'PENDING'], true)) {
                continue;
            }

            $templateChannels = array_map('strtolower', $template['channels'] ?? []);
            $supportsChannel = empty($channels) || count(array_intersect($channels, $templateChannels)) > 0;
            if (! $supportsChannel) {
                continue;
            }

            $name = (string) ($template['name'] ?? '');
            $category = strtoupper((string) ($template['category'] ?? ''));
            $score = 0;

            if ($category === 'AUTHENTICATION') {
                $score += 10;
            }

            if (preg_match('/otp|verification|verify|code|auth/i', $name)) {
                $score += 5;
            }

            if ($preferredName !== '' && str_contains(strtolower($name), $preferredName)) {
                $score += 20;
            }

            if ($score === 0) {
                continue;
            }

            $variables = $template['variables'] ?? [];
            $otpParameter = $this->guessOtpParameter($variables);

            $candidates[] = [
                'score' => $score,
                'id' => (string) $template['id'],
                'otp_parameter' => $otpParameter,
                'name' => $name,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $candidates[0];

        return [
            'id' => $best['id'],
            'otp_parameter' => $best['otp_parameter'],
            'name' => $best['name'],
        ];
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $variables
     */
    private function guessOtpParameter(array $variables): string
    {
        $configured = config('services.sentdm.otp_parameter');
        if (! empty($configured)) {
            return $configured;
        }

        $names = [];
        foreach ($variables as $variable) {
            if (is_string($variable)) {
                $names[] = $variable;
            } elseif (is_array($variable)) {
                $names[] = (string) ($variable['name'] ?? $variable['id'] ?? '');
            }
        }

        foreach (['code', 'otp', 'verification_code', 'verificationCode', 'pin', 'var_1'] as $preferred) {
            if (in_array($preferred, $names, true)) {
                return $preferred;
            }
        }

        foreach ($names as $name) {
            if (preg_match('/^var_\d+$/', $name)) {
                return $name;
            }
        }

        return $names[0] ?? 'code';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTemplates(string $apiKey): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->get(
                rtrim(config('services.sentdm.base_url'), '/').'/v3/templates',
                ['page' => 1, 'page_size' => 50]
            );

            if (! $response->successful()) {
                Log::error('Sent.dm template list failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return [];
            }

            $body = $response->json();

            return $body['data']['templates'] ?? [];
        } catch (\Throwable $e) {
            Log::error('Sent.dm template list error: '.$e->getMessage());

            return [];
        }
    }

    private function formatE164(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '+') && strlen($digits) >= 10) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '234')) {
            return '+'.substr($digits, 0, 13);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+234'.$digits;
        }

        if (str_starts_with($digits, '27')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
