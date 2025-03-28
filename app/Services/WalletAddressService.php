<?php

namespace App\Services;

use App\Models\DepositAddress;
use App\Models\MasterWallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletAddressService
{
    protected $blockchain;
    protected $apiKey;
    protected $apiUrl;

    public function __construct(string $blockchain)
    {
        $this->blockchain = strtoupper($blockchain);
        $this->apiKey = config('tatum.api_key');
        $this->apiUrl = config('tatum.base_url');
    }

    public function generateAndAssignToVirtualAccount($virtualAccount)
    {
        return DB::transaction(function () use ($virtualAccount) {
            // 1. Get Master Wallet
            $masterWallet = MasterWallet::where('blockchain', $this->blockchain)->lockForUpdate()->firstOrFail();
            $xpub = $masterWallet->xpub;
            $mnemonic = $masterWallet->mnemonic;

            // 2. Get next index, start from 2 (0/1 reserved)
            $lastIndex = DepositAddress::where('blockchain', $this->blockchain)->max('index');
            $index = is_null($lastIndex) ? 2 : $lastIndex + 1;

            // 3. Generate deposit address
            $addressResponse = Http::withHeaders([
                'x-api-key' => $this->apiKey,
            ])->get("{$this->apiUrl}/{$this->blockchain}/address/{$xpub}/{$index}");

            if ($addressResponse->failed()) {
                throw new \Exception("Failed to generate address: " . $addressResponse->body());
            }
            // Log::info("Address Response: " . $addressResponse->body());
            Log::info("Address Response: " . json_encode($addressResponse->json()));
            $address = $addressResponse->json('address');

            // 4. Generate private key
            $privateKeyResponse = Http::withHeaders([
                'x-api-key' => $this->apiKey,
            ])->post("{$this->apiUrl}/{$this->blockchain}/wallet/priv", [
                'mnemonic' => $mnemonic,
                'index' => $index,
            ]);

            if ($privateKeyResponse->failed()) {
                throw new \Exception("Failed to generate private key: " . $privateKeyResponse->body());
            }
            Log::info("Private Key Response: " . json_encode($privateKeyResponse->json()));

            $privateKey = $privateKeyResponse->json('key');

            // 5. Assign this address to the virtual account
            $assignResponse = Http::withHeaders([
                'x-api-key' => $this->apiKey,
            ])->post("{$this->apiUrl}/offchain/account/{$virtualAccount->account_id}/address/{$address}");

            // Log full response
            Log::info("Assign Response: " . json_encode($assignResponse->json()));

            // Throw if failed
            if ($assignResponse->failed()) {
                throw new \Exception("Failed to assign deposit address to VA: " . $assignResponse->body());
            }

            // 6. Save everything in DB
            $depositAddress = DepositAddress::create([
                'virtual_account_id' => $virtualAccount->id,
                'blockchain' => $this->blockchain,
                'currency' => $virtualAccount->currency,
                'index' => $index,
                'address' => $address,
                'private_key' => Crypt::encryptString($privateKey),
            ]);

            return $depositAddress;
        });
    }
}
