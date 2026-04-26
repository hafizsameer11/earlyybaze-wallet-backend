<?php

return [
    'api_key' => env('TATUM_API_KEY', ''),
    'base_url' => env('TATUM_BASE_URL', 'https://api.tatum.io/v3'),
    /** Base URL for Notifications API (v4 subscriptions). */
    'v4_base_url' => env('TATUM_V4_BASE_URL', 'https://api.tatum.io/v4'),
    'webhook_url' => 'https://api.settlesys.com/api/webhook',
    'webhook_v2_url' => env('TATUM_WEBHOOK_V2_URL', 'https://api.settlesys.com/api/webhook/v2'),
    /**
     * When true, POST /api/dev/tatum/btc-wallet-v4-subscription responds (otherwise 404).
     * GET /api/dev/tatum/v4-subscribe is registered in routes/api.php and is not gated by this flag.
     */
    'dev_btc_wallet_v4_endpoint_enabled' => filter_var(
        env('TATUM_DEV_BTC_WALLET_V4_ENDPOINT_ENABLED', env('APP_ENV', 'production') === 'local'),
        FILTER_VALIDATE_BOOLEAN
    ),
    /** Chain id for v4 INCOMING_NATIVE_TX (must match your API key network). */
    'v4_btc_chain' => env('TATUM_V4_BTC_CHAIN', 'bitcoin-mainnet'),
];
