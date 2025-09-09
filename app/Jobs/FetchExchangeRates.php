<?php

namespace App\Jobs;

use App\Models\ExchangeRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchExchangeRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // CoinMarketCap symbols
        $apiSymbols = ['BTC', 'ETH', 'BNB', 'TRX', 'LTC'];

        // Log::info('api response is for exchange rate',[
        //     'api_symbols' => $apiSymbols,
        // ]);
        // Mapping CMC symbols to your DB values
        $symbolMap = [
            'BTC' => 'btc',
            'ETH' => 'eth',
            'BNB' => 'bsc',
            'TRX' => 'tron',
            'LTC' => 'ltc',
        ];

        $response = Http::withHeaders([
            'X-CMC_PRO_API_KEY' => 'bf5e27b5-7934-45e2-b3ca-8fd7c5eff261',
            'Accept' => 'application/json',
        ])->get('https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest', [
            'symbol' => implode(',', $apiSymbols),
            'convert' => 'USD',
        ]);

        if (!$response->ok()) {
            // logger()->error('CoinMarketCap API error: ' . $response->body());
            return;
        }

        $data = $response->json('data');    
        Log::info('api response is for exchange rate',[
            'data' => $data]);

        foreach ($symbolMap as $apiSymbol => $dbCurrency) {
            // Log::info("Updating rate_usd for {$dbCurrency}");
            if (!isset($data[$apiSymbol]['quote']['USD']['price'])) {
                // logger()->info();
                // Log::info("Price not found for symbol: {$apiSymbol}");
                continue;
            }

$price = round($data[$apiSymbol]['quote']['USD']['price'], 3); // ⬅️ Keep 6 decimals (adjust as needed)
            // Log::info('prices for exchange rate',[
            //     'price' => $price]);

            ExchangeRate::whereRaw('LOWER(currency) = ?', [strtolower($dbCurrency)])
                ->update(['rate_usd' => $price]);

            // logger()->info("Updated rate_usd for {$dbCurrency}: {$price}");
        }
    }
}
