<?php

namespace App\Http\Controllers;

use App\Models\SentDmWebhookEvent;
use App\Services\SentDmWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SentDmWebhookController extends Controller
{
    public function __construct(
        private SentDmWebhookVerifier $verifier,
    ) {}

    /**
     * Receive Sent.dm delivery status webhooks (message.*, templates.*).
     *
     * Configure in Sent Dashboard → Webhooks:
     *   URL: {APP_URL}/api/webhook/sent
     *   Events: message (all sub-types), templates (optional)
     */
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signatureValid = $this->verifier->verify($request, $rawBody);

        if (! $signatureValid && ! empty(config('services.sentdm.webhook_secret'))) {
            Log::warning('Sent.dm webhook rejected: invalid signature', [
                'webhook_id' => $request->header('x-webhook-id'),
                'event_type' => $request->header('x-webhook-event-type'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $body = json_decode($rawBody, true);
        if (! is_array($body)) {
            Log::warning('Sent.dm webhook rejected: invalid JSON body');

            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $eventKey = $this->buildEventKey($request, $body, $rawBody);
        if (SentDmWebhookEvent::where('event_key', $eventKey)->exists()) {
            return response()->json(['message' => 'OK'], 200);
        }

        $normalized = $this->normalizeEvent($body);
        $normalized['event_key'] = $eventKey;
        $normalized['payload'] = $body;
        $normalized['headers'] = $this->captureHeaders($request);
        $normalized['signature_valid'] = $signatureValid;

        try {
            SentDmWebhookEvent::create($normalized);
        } catch (\Throwable $e) {
            Log::error('Sent.dm webhook store failed', [
                'error' => $e->getMessage(),
                'event_key' => $eventKey,
            ]);
        }

        $this->logEvent($normalized);

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $body): array
    {
        $field = (string) ($body['field'] ?? '');
        $subType = (string) ($body['sub_type'] ?? $body['subType'] ?? '');
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];

        $messageStatus = strtoupper((string) (
            $payload['message_status']
            ?? $payload['status']
            ?? $payload['messageStatus']
            ?? ''
        ));

        $phone = (string) (
            $payload['inbound_number']
            ?? $payload['to']
            ?? $payload['recipient']
            ?? ''
        );

        $failureReason = $this->extractFailureReason($payload, $messageStatus);

        return [
            'field' => $field !== '' ? $field : null,
            'sub_type' => $subType !== '' ? $subType : null,
            'event_type' => $subType !== '' ? $subType : ($field !== '' ? $field : null),
            'message_id' => $this->stringOrNull($payload['message_id'] ?? $payload['messageId'] ?? null),
            'message_status' => $messageStatus !== '' ? $messageStatus : null,
            'channel' => $this->stringOrNull($payload['channel'] ?? null),
            'phone' => $phone !== '' ? $phone : null,
            'template_id' => $this->stringOrNull($payload['template_id'] ?? $payload['templateId'] ?? null),
            'failure_reason' => $failureReason,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractFailureReason(array $payload, string $messageStatus): ?string
    {
        if ($messageStatus !== 'FAILED') {
            return null;
        }

        $candidates = [
            $payload['failure_reason'] ?? null,
            $payload['error_message'] ?? null,
            $payload['error'] ?? null,
            is_array($payload['error'] ?? null) ? ($payload['error']['message'] ?? null) : null,
            $payload['provider_error'] ?? null,
            is_array($payload['provider_error'] ?? null) ? ($payload['provider_error']['message'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function buildEventKey(Request $request, array $body, string $rawBody): string
    {
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        $messageId = (string) ($payload['message_id'] ?? $payload['messageId'] ?? '');
        $subType = (string) ($body['sub_type'] ?? $body['subType'] ?? $request->header('x-webhook-event-type', ''));
        $status = strtoupper((string) ($payload['message_status'] ?? $payload['status'] ?? ''));
        $timestamp = (string) $request->header('x-webhook-timestamp', '');

        if ($messageId !== '' && ($subType !== '' || $status !== '')) {
            return hash('sha256', implode('|', [$messageId, $subType, $status, $timestamp]));
        }

        return hash('sha256', $rawBody);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function logEvent(array $event): void
    {
        $context = [
            'message_id' => $event['message_id'] ?? null,
            'status' => $event['message_status'] ?? null,
            'phone' => $event['phone'] ?? null,
            'channel' => $event['channel'] ?? null,
            'template_id' => $event['template_id'] ?? null,
            'sub_type' => $event['sub_type'] ?? null,
            'failure_reason' => $event['failure_reason'] ?? null,
        ];

        $status = strtoupper((string) ($event['message_status'] ?? ''));

        if ($status === 'FAILED') {
            Log::error('Sent.dm message delivery FAILED', $context);

            return;
        }

        if (in_array($status, ['DELIVERED', 'READ', 'SENT'], true)) {
            Log::info('Sent.dm message status update', $context);

            return;
        }

        Log::info('Sent.dm webhook received', $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureHeaders(Request $request): array
    {
        return [
            'x-webhook-signature' => $request->header('x-webhook-signature'),
            'x-webhook-id' => $request->header('x-webhook-id'),
            'x-webhook-timestamp' => $request->header('x-webhook-timestamp'),
            'x-webhook-event-type' => $request->header('x-webhook-event-type'),
            'user-agent' => $request->userAgent(),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
