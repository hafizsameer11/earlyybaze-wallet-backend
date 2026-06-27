<?php

namespace Tests\Unit;

use App\Services\TatumOnChainTxVerifier;
use App\Support\TatumTxResponseParser;
use Tests\TestCase;

class TatumOnChainTxVerifierTest extends TestCase
{
    private function loadFixture(string $sampleId): array
    {
        $path = base_path('docs/tatum_tx_verification_results.json');
        $data = json_decode((string) file_get_contents($path), true);
        $sample = $data['samples'][$sampleId] ?? null;
        $this->assertNotNull($sample, "Missing fixture {$sampleId}");

        return $sample['v4']['body'] ?? $sample['v3']['body'];
    }

    public function test_btc_parser_extracts_output_address_and_amount(): void
    {
        $body = $this->loadFixture('btc_native');
        $transfers = TatumTxResponseParser::extractTransfers($body, 'utxo', 'BTC');

        $this->assertNotEmpty($transfers);
        $this->assertSame('1PuJjnF476W3zXfVYmJfGnouzFDAXakkL4', $transfers[0]['to']);
        $this->assertSame('3.14117135', $transfers[0]['amount']);
        $this->assertTrue(TatumTxResponseParser::isConfirmed($body, 'utxo'));
    }

    public function test_eth_native_parser(): void
    {
        $body = $this->loadFixture('eth_native');
        $transfers = TatumTxResponseParser::extractTransfers($body, 'evm_native', 'ETH');

        $this->assertNotEmpty($transfers);
        $this->assertSame('0xae38b2153413f1f8438340963f298e39e3b25e04', strtolower($transfers[0]['to']));
        $this->assertTrue(TatumTxResponseParser::isConfirmed($body, 'evm_native'));
    }

    public function test_usdt_eth_parser_finds_transfer_log(): void
    {
        $body = $this->loadFixture('usdt_eth');
        $transfers = TatumTxResponseParser::extractTransfers($body, 'evm_token', 'USDT');

        $this->assertNotEmpty($transfers);
        $recipient = strtolower($transfers[0]['to']);
        $this->assertStringContainsString('e2e7a17d', $recipient);
        $this->assertSame('0xdac17f958d2ee523a2206206994597c13d831ec7', strtolower($transfers[0]['contract']));
    }

    public function test_usdt_bsc_parser(): void
    {
        $body = $this->loadFixture('usdt_bsc');
        $transfers = TatumTxResponseParser::extractTransfers($body, 'evm_token', 'USDT_BSC');

        $this->assertNotEmpty($transfers);
        $this->assertSame('0x55d398326f99059ff775485246999027b3197955', strtolower($transfers[0]['contract']));
    }

    public function test_tron_native_parser(): void
    {
        $body = $this->loadFixture('trx_native');
        $transfers = TatumTxResponseParser::extractTransfers($body, 'tron_native', 'TRX');

        $this->assertNotEmpty($transfers);
        $this->assertSame('TYt7r4SHSjc4XPHE4FpetgYgK5SMwRrTHm', $transfers[0]['to']);
        $this->assertSame('1.00000000', $transfers[0]['amount']);
    }

    public function test_usdt_tron_parser(): void
    {
        $body = $this->loadFixture('usdt_tron');
        $transfers = TatumTxResponseParser::extractTransfers($body, 'tron_token', 'USDT_TRON');

        $this->assertNotEmpty($transfers);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $transfers[0]['contract']);
    }

    public function test_verifier_parse_body_eth_native(): void
    {
        $verifier = new TatumOnChainTxVerifier;
        $body = $this->loadFixture('eth_native');
        $result = $verifier->parseBody($body, 'ETH');

        $this->assertTrue($result->found);
        $this->assertTrue($result->confirmed);
        $this->assertNotNull($result->to);
    }

    public function test_btc_flush_sums_multiple_outputs_to_same_address(): void
    {
        $verifier = new TatumOnChainTxVerifier;
        $body = [
            'blockNumber' => 955568,
            'outputs' => [
                [
                    'value' => 2396017,
                    'address' => 'bc1quz7cf4uznl2drw5gd7me24wqfpflxu5ukktmpfyzaa8snw9cj28q0aepw9',
                ],
                [
                    'value' => 1294382,
                    'address' => 'bc1quz7cf4uznl2drw5gd7me24wqfpflxu5ukktmpfyzaa8snw9cj28q0aepw9',
                ],
            ],
        ];

        $result = $verifier->verifyFlush(
            'BTC',
            '32d61597091d7da48a48f79e4698f0d9cc410f680f772fc6a761bbe61702aa7f',
            null,
            'bc1quz7cf4uznl2drw5gd7me24wqfpflxu5ukktmpfyzaa8snw9cj28q0aepw9',
            '0.03690399',
            null,
            $body,
        );

        $this->assertTrue($result->matches);
        $this->assertSame('0.03690399', $result->amount);
    }

    public function test_btc_flush_matches_batch_input_total_when_fee_is_deducted_on_chain(): void
    {
        $verifier = new TatumOnChainTxVerifier;
        $body = [
            'blockNumber' => 955568,
            'fee' => 7836,
            'outputs' => [
                [
                    'value' => 2396017,
                    'address' => 'bc1quz7cf4uznl2drw5gd7me24wqfpflxu5ukktmpfyzaa8snw9cj28q0aepw9',
                ],
                [
                    'value' => 1294382,
                    'address' => 'bc1quz7cf4uznl2drw5gd7me24wqfpflxu5ukktmpfyzaa8snw9cj28q0aepw9',
                ],
            ],
        ];

        $result = $verifier->verifyFlush(
            'BTC',
            '32d61597091d7da48a48f79e4698f0d9cc410f680f772fc6a761bbe61702aa7f',
            null,
            'bc1quz7cf4uznl2drw5gd7me24wqfpflxu5ukktmpfyzaa8snw9cj28q0aepw9',
            '0.03698235',
            null,
            $body,
        );

        $this->assertTrue($result->matches);
    }
}
