<?php

namespace Tests\Unit;

use App\Services\TatumOnChainTxVerifier;
use App\Support\TatumChainMapper;
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
        $this->assertSame(18, TatumChainMapper::tokenDecimals('USDT_BSC'));
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

    public function test_usdt_flush_parses_recipient_with_leading_zero_byte(): void
    {
        $verifier = new TatumOnChainTxVerifier;
        $body = [
            'hash' => '0xa1efa98d7fb034f6ed31efb0b66d7ec5a389082f9b8af8f1e4428a7f2ea2641d',
            'from' => '0xb00fa5305000ed990f6964f5476d4e950a14692d',
            'to' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'value' => '0',
            'status' => true,
            'blockNumber' => 25409579,
            'logs' => [[
                'address' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
                'data' => '0x0000000000000000000000000000000000000000000000000000000000b74fc2',
                'topics' => [
                    '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                    '0x000000000000000000000000b00fa5305000ed990f6964f5476d4e950a14692d',
                    '0x0000000000000000000000000b9bfb82322f65635d851ef835aaee2f8fb72111',
                ],
                'logIndex' => 790,
            ]],
        ];

        $result = $verifier->verifyFlush(
            'USDT',
            $body['hash'],
            '0xb00fa5305000ed990f6964f5476d4e950a14692d',
            '0x0b9BFb82322f65635d851Ef835aaeE2F8fb72111',
            '12.01350600',
            null,
            $body,
        );

        $this->assertTrue($result->matches);
        $this->assertSame('12.01350600', $result->amount);
        $this->assertSame('0x0b9bfb82322f65635d851ef835aaee2f8fb72111', strtolower((string) $result->to));
    }

    public function test_usdt_bsc_flush_uses_18_decimal_places(): void
    {
        $verifier = new TatumOnChainTxVerifier;
        $body = [
            'hash' => '0xd1c3ef0fc11cb63388acca40e3b169bc2818f81ba2d7bed6dbe1c6637fa6d705',
            'from' => '0x2e5189f4d8e8bc7f1f65f646c5514b9b0177f360',
            'to' => '0x55d398326f99059ff775485246999027b3197955',
            'value' => '0',
            'status' => true,
            'blockNumber' => 106689600,
            'logs' => [[
                'address' => '0x55d398326f99059ff775485246999027b3197955',
                'data' => '0x000000000000000000000000000000000000000000000002d30861f65dda0000',
                'topics' => [
                    '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                    '0x0000000000000000000000002e5189f4d8e8bc7f1f65f646c5514b9b0177f360',
                    '0x0000000000000000000000000b9bfb82322f65635d851ef835aaee2f8fb72111',
                ],
                'logIndex' => 112,
            ]],
        ];

        $result = $verifier->verifyFlush(
            'USDT_BSC',
            $body['hash'],
            '0x2e5189f4d8e8bc7f1f65f646c5514b9b0177f360',
            '0x0b9BFb82322f65635d851Ef835aaeE2F8fb72111',
            '52.10000000',
            null,
            $body,
        );

        $this->assertTrue($result->matches);
        $this->assertSame('52.10000000', $result->amount);
    }
}
