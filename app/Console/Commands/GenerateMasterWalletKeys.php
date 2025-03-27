<?php

namespace App\Console\Commands;

use App\Models\MasterWallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMasterWalletKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:generate-master-keys';

    protected $description = 'Generate private keys and addresses for all master wallets';

    protected $apiKey;
    protected $baseUrl;
    /**
     * Execute the console command.
     */
    public function __construct()
    {
        parent::__construct();
        $this->apiKey = config('tatum.api_key');
        $this->baseUrl = config('tatum.base_url');
    }

    public function handle()
    {
        $wallets = MasterWallet::whereNull('private_key')
            ->orWhereNull('address')
            ->get();

        if ($wallets->isEmpty()) {
            $this->info("✅ All master wallets already have keys and addresses.");
            return;
        }

        foreach ($wallets as $wallet) {
            $blockchain = strtolower($wallet->blockchain); // e.g. eth, btc, tron
            $this->info("🔄 Processing: {$wallet->blockchain}");

            try {
                // Derive blockchain address (index 0)
                $addressRes = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                ])->get("{$this->baseUrl}/{$blockchain}/address/{$wallet->xpub}/0");

                $address = $addressRes->json()['address'] ?? null;
                //log response
                Log::info("Address generation response for {$wallet->blockchain}: " . json_encode($addressRes->json()));

                // Generate private key using mnemonic
                $privKeyRes = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                ])->post("{$this->baseUrl}/{$blockchain}/wallet/priv", [
                    'mnemonic' => $wallet->mnemonic,
                    'index' => 0,
                ]);
                //log response
                Log::info("Private keys generation response for {$wallet->blockchain}: " . json_encode($privKeyRes->json()));
                $privateKey = $privKeyRes->json()['key'] ?? null;

                if (!$address || !$privateKey) {
                    $this->error("❌ Incomplete response for {$wallet->blockchain}. Skipping...");
                    continue;
                }

                // Encrypt and save to DB
                $encryptedKey = Crypt::encrypt($privateKey);

                $wallet->update([
                    'private_key' => $encryptedKey,
                    'address' => $address,
                ]);

                $this->info("✅ DB updated with encrypted key for {$wallet->blockchain}");

                // Save unencrypted to TXT for manual .env copy
                $envKey = "MASTER_WALLET_PRIVATE_KEY_" . strtoupper($wallet->blockchain);
                $envAddr = "MASTER_WALLET_ADDRESS_" . strtoupper($wallet->blockchain);
                $entry = "{$envKey}={$privateKey}\n{$envAddr}={$address}\n\n";

                Storage::disk('local')->append('wallet_keys.txt', $entry);

                $this->info("📝 Saved key to storage/app/wallet_keys.txt for {$wallet->blockchain}");
            } catch (\Exception $e) {
                Log::error("Wallet generation failed for {$wallet->blockchain}: " . $e->getMessage());
                $this->error("❌ Exception: " . $e->getMessage());
            }
        }

        $this->info("🎉 All missing keys processed.");
    }
}
