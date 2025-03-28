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
}
