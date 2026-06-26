<?php

/**
 * Known mainnet transactions for Tatum tx-verification exploration.
 * Hashes are public on-chain; metadata helps compare webhook fields later.
 *
 * Run: php artisan tatum:verify-tx-samples
 * Output: docs/tatum_tx_verification_results.json
 */
return [
    'samples' => [
        [
            'id' => 'btc_native',
            'label' => 'BTC native transfer (coinbase/miner payout)',
            'currency' => 'BTC',
            'chain_v4' => 'bitcoin-mainnet',
            'chain_v3' => 'bitcoin',
            'tx_hash' => 'f43ad37b3bd9fb4830a064cc8a935472b926c5ac777d8c05820a42dc84d6d0df',
            'expected_to' => '1PuJjnF476W3zXfVYmJfGnouzFDAXakkL4',
            'notes' => 'UTXO — inspect outputs[] / vouts for address + value (satoshis)',
        ],
        [
            'id' => 'eth_native',
            'label' => 'ETH native transfer',
            'currency' => 'ETH',
            'chain_v4' => 'ethereum-mainnet',
            'chain_v3' => 'ethereum',
            'tx_hash' => '0x6e213fa1a3845644e4f1f0c7a3b151d3def67dec14be3356519fa44b7b7067c2',
            'expected_to' => '0xAe38b2153413f1f8438340963F298E39e3b25E04',
            'notes' => 'EVM native — from, to, value (wei string)',
        ],
        [
            'id' => 'usdt_eth',
            'label' => 'USDT ERC-20 on Ethereum',
            'currency' => 'USDT',
            'chain_v4' => 'ethereum-mainnet',
            'chain_v3' => 'ethereum',
            'tx_hash' => '0x237dbb4029db22acecdd8ca4dd1a8a0a502a8c59e7c2b14804ff8e8bd48d12c8',
            'contract' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'expected_to' => '0xe2e7a17dFf93280dec073C995595155283e3C372',
            'notes' => 'Token — decode logs / token transfer; contract must match allowlist',
        ],
        [
            'id' => 'usdc_eth',
            'label' => 'USDC ERC-20 on Ethereum',
            'currency' => 'USDC',
            'chain_v4' => 'ethereum-mainnet',
            'chain_v3' => 'ethereum',
            'tx_hash' => '0x65bcd7f70211f64fb273f055e2b1608639d39ccdb545101d64d8eaada2ecc2af',
            'contract' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'notes' => 'Token — same shape as USDT on ETH',
        ],
        [
            'id' => 'bnb_native',
            'label' => 'BNB native transfer (BSC block builder)',
            'currency' => 'BNB',
            'chain_v4' => 'bsc-mainnet',
            'chain_v3' => 'bsc',
            'tx_hash' => '0x913c648e4cb953a82efc962d31708f20f70a8591ae73bff901d227b96744bcb0',
            'expected_to' => '0x4848489f0b2bedd788c696e2d79b6b69d7484848',
            'notes' => 'EVM on BSC — from, to, value; chainId 0x38',
        ],
        [
            'id' => 'usdt_bsc',
            'label' => 'USDT BEP-20 on BSC',
            'currency' => 'USDT_BSC',
            'chain_v4' => 'bsc-mainnet',
            'chain_v3' => 'bsc',
            'tx_hash' => '0xdd09281a1f6d7b062cd040b768757848740d474dc09a12aaf3a9fd15191236fa',
            'contract' => '0x55d398326f99059ff775485246999027b3197955',
            'notes' => 'BEP-20 — verify contract + recipient + amount from logs',
        ],
        [
            'id' => 'trx_native',
            'label' => 'TRX native transfer',
            'currency' => 'TRX',
            'chain_v4' => 'tron-mainnet',
            'chain_v3' => 'tron',
            'tx_hash' => '867f85d48b6cbab79370800bb78a5d3fb46083d70c211e7cbd3fbfe4fb26eb80',
            'expected_to' => 'TYt7r4SHSjc4XPHE4FpetgYgK5SMwRrTHm',
            'notes' => 'Tron native — txID, rawData.contract TransferContract, amount in sun',
        ],
        [
            'id' => 'usdt_tron',
            'label' => 'USDT TRC-20 on Tron',
            'currency' => 'USDT_TRON',
            'chain_v4' => 'tron-mainnet',
            'chain_v3' => 'tron',
            'tx_hash' => 'b2f3ae64f007370f582587acff6a973a18f3481fb3eb635f8022f994182ba61d',
            'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'notes' => 'TRC-20 — TriggerSmartContract, contractAddressBase58, transfer data in rawData',
        ],
    ],
];
