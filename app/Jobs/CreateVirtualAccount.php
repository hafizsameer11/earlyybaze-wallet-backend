<?php

namespace App\Jobs;

use App\Models\MasterWallet;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateVirtualAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $user;
    protected $apiKey;
    protected $apiUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->apiKey = config('tatum.api_key');
        $this->apiUrl = config('tatum.base_url');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // Fetch all supported wallet currencies
            $walletCurrencies = WalletCurrency::all();

            foreach ($walletCurrencies as $walletCurrency) {
                // Fetch the corresponding master wallet for the blockchain
                $masterWallet = MasterWallet::where('blockchain', $walletCurrency->blockchain)->first();
                if (!$masterWallet) {
                    Log::error("No master wallet found for blockchain: " . $walletCurrency->blockchain);
                    continue;
                }
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                ])->post($this->apiUrl . '/ledger/account', [
                    'currency' => $walletCurrency->currency,
                    'xpub' => $masterWallet->xpub,
                    'customer' => [
                        'externalId' => (string) $this->user->id,
                    ],
                    'accountCode' => $this->user->user_code,
                    'accountingCurrency' => 'USD',
                ]);

                // Check response status
                if ($response->failed()) {
                    Log::error("Failed to create virtual account for user {$this->user->id}: " . $response->body());
                    continue;
                }

                $accountData = $response->json();

                $virtualAccount = VirtualAccount::create([
                    'user_id' => $this->user->id,
                    'blockchain' => $walletCurrency->blockchain,
                    'currency' => $walletCurrency->currency,
                    'customer_id' => $accountData['customerId'],
                    'account_id' => $accountData['id'],
                    'account_code' => $this->user->user_code,
                    'active' => $accountData['active'],
                    'frozen' => $accountData['frozen'],
                    'account_balance' => $accountData['balance']['accountBalance'],
                    'available_balance' => $accountData['balance']['availableBalance'],
                    'xpub' => $accountData['xpub'],
                    'accounting_currency' => $accountData['accountingCurrency'],
                    'currency_id' => $walletCurrency->id,
                ]);

                Log::info("Virtual account created and stored for user {$this->user->id}: ", $accountData);

                dispatch(new AssignDepositAddress($virtualAccount));
            }
        } catch (\Exception $e) {
            Log::error("CreateVirtualAccount Job failed: " . $e->getMessage());
        }
    }
}
