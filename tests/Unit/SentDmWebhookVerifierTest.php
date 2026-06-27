<?php

namespace Tests\Unit;

use App\Services\SentDmWebhookVerifier;
use Illuminate\Http\Request;
use Tests\TestCase;

class SentDmWebhookVerifierTest extends TestCase
{
    public function test_verifies_valid_signature(): void
    {
        $secret = 'whsec_'.base64_encode('test-signing-key-bytes!!');
        config(['services.sentdm.webhook_secret' => $secret]);

        $webhookId = '550e8400-e29b-41d4-a716-446655440000';
        $timestamp = (string) time();
        $rawBody = '{"field":"message","sub_type":"message.failed","payload":{"message_id":"msg-1","message_status":"FAILED","inbound_number":"+2348012345678","channel":"whatsapp"}}';

        $signedContent = $webhookId.'.'.$timestamp.'.'.$rawBody;
        $keyBytes = base64_decode(substr($secret, 6));
        $hash = base64_encode(hash_hmac('sha256', $signedContent, $keyBytes, true));
        $signature = 'v1,'.$hash;

        $request = Request::create('/api/webhook/sent', 'POST', [], [], [], [], $rawBody);
        $request->headers->set('x-webhook-signature', $signature);
        $request->headers->set('x-webhook-id', $webhookId);
        $request->headers->set('x-webhook-timestamp', $timestamp);

        $verifier = new SentDmWebhookVerifier;

        $this->assertTrue($verifier->verify($request, $rawBody));
    }

    public function test_rejects_tampered_payload(): void
    {
        $secret = 'whsec_'.base64_encode('test-signing-key-bytes!!');
        $webhookId = '550e8400-e29b-41d4-a716-446655440000';
        $timestamp = (string) time();
        $rawBody = '{"field":"message","payload":{"message_status":"FAILED"}}';

        $signedContent = $webhookId.'.'.$timestamp.'.'.$rawBody;
        $keyBytes = base64_decode(substr($secret, 6));
        $hash = base64_encode(hash_hmac('sha256', $signedContent, $keyBytes, true));

        $request = Request::create('/api/webhook/sent', 'POST', [], [], [], [], $rawBody.' ');
        $request->headers->set('x-webhook-signature', 'v1,'.$hash);
        $request->headers->set('x-webhook-id', $webhookId);
        $request->headers->set('x-webhook-timestamp', $timestamp);

        config(['services.sentdm.webhook_secret' => $secret]);

        $verifier = new SentDmWebhookVerifier;

        $this->assertFalse($verifier->verify($request, $rawBody.' '));
    }
}
