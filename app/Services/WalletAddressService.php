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
        $this->blockchain = strtolower($blockchain);
        $this->apiKey = config('tatum.api_key');
        $this->apiUrl = config('tatum.base_url');
    }

    public function generateAndAssignToVirtualAccount($virtualAccount)
    {
        return DB::transaction(function () use ($virtualAccount) {
            $userId = $virtualAccount->user_id;

            // Blockchain groups for shared address
            $addressGroups = [
                'tron' => ['tron', 'usdt_tron'],
                'ethereum' => ['eth', 'usdt', 'usdc'],
                'bsc' => ['bsc', 'usdt_bsc', 'usdc_bsc'],
            ];
            Log::info("Currenct Blockchain: {$this->blockchain}");
            $groupBlockchains = collect($addressGroups)->first(function ($group) {
                return in_array($this->blockchain, $group);
            });
            Log::info("Group Blockchains: " . json_encode($groupBlockchains));
            // 1. Check for existing address in the same group for this user
            $existing = DepositAddress::where('blockchain', $this->blockchain)
            ->whereHas('virtualAccount', fn($q) => $q->where('user_id', $userId))
            ->first();


            if ($existing) {
                // Reuse existing address and private key
                Http::withHeaders(['x-api-key' => $this->apiKey])
                    ->post("{$this->apiUrl}/offchain/account/{$virtualAccount->account_id}/address/{$existing->address}");

                return DepositAddress::create([
                    'virtual_account_id' => $virtualAccount->id,
                    'blockchain' => strtolower($virtualAccount->blockchain),
                    'currency' => $virtualAccount->currency,
                    'index' => $existing->index,
                    'address' => $existing->address,
                    'private_key' => $existing->private_key,
                ]);
            }

            // 2. Get master wallet
            $masterWallet = MasterWallet::where('blockchain', $this->blockchain)->lockForUpdate()->firstOrFail();
            $xpub = $masterWallet->xpub;
            $mnemonic = $masterWallet->mnemonic;

            // 3. Get next index (start from 5)
            $lastIndex = DepositAddress::where('blockchain', $this->blockchain)->max('index');
            $index = is_null($lastIndex) ? 5 : $lastIndex + 20;

            // 4. Generate deposit address
            $addressResponse = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->get("{$this->apiUrl}/{$this->blockchain}/address/{$xpub}/{$index}");

            if ($addressResponse->failed()) {
                throw new \Exception("Failed to generate address: " . $addressResponse->body());
            }

            $address = $addressResponse->json('address');
            Log::info("Generated Address: $address (index: $index)");

            // 5. Generate private key
            $privateKeyResponse = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->post("{$this->apiUrl}/{$this->blockchain}/wallet/priv", [
                    'mnemonic' => $mnemonic,
                    'index' => $index,
                ]);

            if ($privateKeyResponse->failed()) {
                throw new \Exception("Failed to generate private key: " . $privateKeyResponse->body());
            }

            $privateKey = $privateKeyResponse->json('key');

            // 6. Assign to virtual account
            $assignResponse = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->post("{$this->apiUrl}/offchain/account/{$virtualAccount->account_id}/address/{$address}");

            if ($assignResponse->failed()) {
                throw new \Exception("Failed to assign address to VA: " . $assignResponse->body());
            }

            // 7. Save to DB
            return DepositAddress::create([
                'virtual_account_id' => $virtualAccount->id,
                'blockchain' => strtolower($this->blockchain),
                'currency' => $virtualAccount->currency,
                'index' => $index,
                'address' => $address,
                'private_key' => Crypt::encryptString($privateKey),
            ]);
        });
    }
}
