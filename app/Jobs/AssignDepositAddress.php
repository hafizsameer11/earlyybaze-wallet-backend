<?php

namespace App\Jobs;

use App\Models\DepositAddress;
use App\Models\VirtualAccount;
use App\Services\WalletAddressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssignDepositAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $virtualAccount;

    public function __construct(VirtualAccount $virtualAccount)
    {
        $this->virtualAccount = $virtualAccount;
    }

    public function handle()
    {
        try {
            // Step 1: Generate address + private key from master wallet
            $service = new WalletAddressService($this->virtualAccount->blockchain);
            $wallet = $service->generateAndAssignToVirtualAccount($this->virtualAccount);

            // Step 2: Save in DB
            DepositAddress::create([
                'virtual_account_id' => $this->virtualAccount->id,
                'blockchain' => $this->virtualAccount->blockchain,
                'currency' => $this->virtualAccount->currency,
                'index' => $wallet['index'],
                'address' => $wallet['address'],
                'private_key' => $wallet['private_key'],
            ]);

            Log::info("✅ Deposit address assigned for VA ID {$this->virtualAccount->id}: {$wallet['address']}");
        } catch (\Exception $e) {
            Log::error("❌ AssignDepositAddress Job failed for VA ID {$this->virtualAccount->id}: " . $e->getMessage());
        }
    }
    // protected $virtualAccount;
    // protected $apiKey;
    // protected $apiUrl;

    // /**
    //  * Create a new job instance.
    //  */
    // public function __construct(VirtualAccount $virtualAccount)
    // {
    //     $this->virtualAccount = $virtualAccount;
    //     $this->apiKey = config('tatum.api_key');
    //     $this->apiUrl = config('tatum.base_url');
    // }

    // /**
    //  * Execute the job.
    //  */
    // public function handle()
    // {
    //     try {
    //         // Call Tatum API to assign a deposit address
    //         $response = Http::withHeaders([
    //             'x-api-key' => $this->apiKey,
    //         ])->post("$this->apiUrl/offchain/account/{$this->virtualAccount->account_id}/address");

    //         // Check response
    //         if ($response->failed()) {
    //             Log::error("Failed to assign deposit address for virtual account ID {$this->virtualAccount->id}: " . $response->body());
    //             return;
    //         }

    //         $data = $response->json();
    //         $depositAddress = $data['address'] ?? null; // Extract deposit address

    //         // Save deposit address to the deposit_addresses table
    //         DepositAddress::create([
    //             'virtual_account_id' => $this->virtualAccount->id,
    //             'blockchain' => $this->virtualAccount->blockchain,
    //             'currency' => $this->virtualAccount->currency,
    //             'address' => $depositAddress,
    //         ]);

    //         Log::info("Deposit address assigned for virtual account ID {$this->virtualAccount->id}: {$depositAddress}");
    //     } catch (\Exception $e) {
    //         Log::error("AssignDepositAddress Job failed: " . $e->getMessage());
    //     }
    // }
}
