<?php

namespace App\Services;

use Illuminate\Http\Request;

class SentDmWebhookVerifier
{
    public function verify(Request $request, string $rawBody): bool
    {
        $secret = config('services.sentdm.webhook_secret');
        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('x-webhook-signature');
        $webhookId = $request->header('x-webhook-id');
        $timestamp = $request->header('x-webhook-timestamp');

        if (! is_string($signature) || ! is_string($webhookId) || ! is_string($timestamp)) {
            return false;
        }

        if (! $this->timestampIsRecent($timestamp)) {
            return false;
        }

        return $this->signaturesMatch($signature, $webhookId, $timestamp, $rawBody, $secret);
    }

    private function timestampIsRecent(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $maxSkew = (int) config('services.sentdm.webhook_timestamp_tolerance', 300);

        return abs(time() - (int) $timestamp) <= $maxSkew;
    }

    private function signaturesMatch(
        string $signature,
        string $webhookId,
        string $timestamp,
        string $rawBody,
        string $secret,
    ): bool {
        [$version, $hash] = array_pad(explode(',', $signature, 2), 2, null);
        if ($version !== 'v1' || ! is_string($hash) || $hash === '') {
            return false;
        }

        $key = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $keyBytes = base64_decode($key, true);
        if ($keyBytes === false) {
            return false;
        }

        $signedContent = $webhookId.'.'.$timestamp.'.'.$rawBody;
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $keyBytes, true));

        return hash_equals($expected, $hash);
    }
}
