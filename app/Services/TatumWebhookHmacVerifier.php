<?php

namespace App\Services;

use App\Models\TatumWebhookHmacVerificationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TatumWebhookHmacVerifier
{
    public const HEADER_NAME = 'x-payload-hash';

    public function verifyOrAllow(Request $request, string $channel): bool
    {
        $secret = (string) config('tatum.webhook_hmac_secret', '');
        $enforce = filter_var(config('tatum.webhook_hmac_enforce', false), FILTER_VALIDATE_BOOLEAN);

        $provided = trim((string) $request->header(self::HEADER_NAME));
        $rawBody = (string) $request->getContent();

        $rawBodyLen = strlen($rawBody);
        $requestId = (string) ($request->header('x-request-id') ?? $request->header('X-Request-Id') ?? '');

        if ($secret === '') {
            $this->recordEvent($channel, $request, [
                'hmac_enabled' => false,
                'enforce' => $enforce,
                'verified' => true,
                'provided_payload_hash' => $provided !== '' ? $provided : null,
                'computed_payload_hash' => null,
                'failure_reason' => 'secret_empty',
                'raw_body_len' => $rawBodyLen,
                'request_id' => $requestId !== '' ? $requestId : null,
            ]);

            return true;
        }

        $computed = base64_encode(hash_hmac('sha512', $rawBody, $secret, true));

        $verified = ($provided !== '' && hash_equals($computed, $provided));
        $failureReason = null;
        if (! $verified) {
            $failureReason = $provided === '' ? 'missing_header' : 'mismatch';
        }

        $this->recordEvent($channel, $request, [
            'hmac_enabled' => true,
            'enforce' => $enforce,
            'verified' => $verified,
            'provided_payload_hash' => $provided !== '' ? $provided : null,
            'computed_payload_hash' => $computed,
            'failure_reason' => $failureReason,
            'raw_body_len' => $rawBodyLen,
            'request_id' => $requestId !== '' ? $requestId : null,
        ]);

        if ($verified) {
            return true;
        }

        Log::warning('Tatum webhook HMAC verification failed', [
            'header' => self::HEADER_NAME,
            'enforce' => $enforce,
            'failure_reason' => $failureReason,
            // Avoid logging payload/body contents or secrets.
            'raw_body_len' => $rawBodyLen,
        ]);

        return ! $enforce;
    }

    /**
     * Best-effort audit logging for every webhook verification.
     * Never throws to callers.
     *
     * @param  array<string, mixed>  $data
     */
    private function recordEvent(string $channel, Request $request, array $data): void
    {
        try {
            TatumWebhookHmacVerificationEvent::create([
                'channel' => $channel,
                'path' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $data['request_id'] ?? null,
                'hmac_enabled' => (bool) ($data['hmac_enabled'] ?? false),
                'enforce' => (bool) ($data['enforce'] ?? false),
                'verified' => (bool) ($data['verified'] ?? false),
                'provided_payload_hash' => $data['provided_payload_hash'] ?? null,
                'computed_payload_hash' => $data['computed_payload_hash'] ?? null,
                'failure_reason' => $data['failure_reason'] ?? null,
                'raw_body_len' => (int) ($data['raw_body_len'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Tatum webhook HMAC event store failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

