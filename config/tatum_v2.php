<?php

return [
    'v4_network_type' => env('TATUM_V4_NETWORK_TYPE', 'mainnet'),

    /**
     * Wallet v2: only these chain_keys get Tatum wallets + v4 subs (BTC, ETH, BSC, TRON).
     */
    'v2_allowed_chain_keys' => ['bitcoin', 'ethereum', 'bsc', 'tron'],

    /** wallet_currencies.currency values never provisioned for wallet v2 */
    'v2_excluded_wallet_currencies' => ['LTC', 'SOL'],

    'v2_stablecoin_currencies' => [
        'USDT', 'USDC',
        'USDT_BSC', 'USDC_BSC',
        'USDT_TRON', 'USDC_TRON',
        'USDT_ETH', 'USDC_ETH',
    ],

    'v2_native_currencies_by_chain' => [
        'bitcoin' => ['BTC'],
        'ethereum' => ['ETH'],
        'bsc' => ['BNB', 'BSC'],
        'tron' => ['TRON', 'TRX'],
    ],

    'chain_profiles' => [
        'bitcoin' => [
            'wallet_endpoint' => '/bitcoin/wallet',
            'address_prefix' => 'bitcoin',
            'v4_chain' => 'bitcoin-mainnet',
            'v4_chain_testnet' => 'bitcoin-testnet',
        ],
        'ethereum' => [
            'wallet_endpoint' => '/ethereum/wallet',
            'address_prefix' => 'ethereum',
            'v4_chain' => 'ethereum-mainnet',
            'v4_chain_testnet' => 'ethereum-sepolia',
        ],
        'bsc' => [
            'wallet_endpoint' => '/bsc/wallet',
            'address_prefix' => 'bsc',
            'v4_chain' => 'bsc-mainnet',
            'v4_chain_testnet' => 'bsc-testnet',
        ],
        'tron' => [
            'wallet_endpoint' => '/tron/wallet',
            'address_prefix' => 'tron',
            'v4_chain' => 'tron-mainnet',
            'v4_chain_testnet' => 'tron-testnet',
        ],
    ],

    'blockchain_aliases' => [
        'btc' => 'bitcoin',
        'eth' => 'ethereum',
        'binance-smart-chain' => 'bsc',
    ],

    'shared_address_groups' => [
        'ethereum' => ['ethereum', 'eth', 'usdt', 'usdc'],
        'bsc' => ['bsc', 'binance-smart-chain', 'usdt_bsc', 'usdc_bsc'],
        'tron' => ['tron', 'usdt_tron', 'usdc_tron'],
    ],
];
