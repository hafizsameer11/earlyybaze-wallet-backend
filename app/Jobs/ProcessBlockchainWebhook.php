<?php

namespace App\Jobs;

use App\Helpers\ExchangeFeeHelper;
use App\Models\FailedMasterTransfer;
use App\Models\MasterWallet;
use App\Models\ReceiveTransaction;
use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
use App\Repositories\transactionRepository;
use App\Services\BitcoinService;
// use App\Services\BitcoinService;
// use App\Services\LitecoinService;
use App\Services\BscService;
use App\Services\EthereumService;
use App\Services\LitecoinService;
use App\Services\SolanaService;
use App\Services\TronTransferService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessBlockchainWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    protected $data;

    protected $transactionRepository;
    protected $EthService, $BscService, $BitcoinService, $SolanaService, $LitecoinService, $TronTransferService;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(transactionRepository $transactionRepository, EthereumService $EthService, TronTransferService $TronTransferService, BscService $BscService, BitcoinService $BitcoinService, SolanaService $SolanaService, LitecoinService $LitecoinService)
    {
        $this->transactionRepository = $transactionRepository;
        $this->EthService = $EthService;
        $this->BscService = $BscService;
        $this->BitcoinService = $BitcoinService;
        $this->SolanaService = $SolanaService;
        $this->LitecoinService = $LitecoinService;
        $this->TronTransferService = $TronTransferService;


        $data = $this->data;

        Log::info('🔁 Processing Webhook:', $data);

        $from = $data['from'] ?? null;
        // if (!$from) return;

        if ($from) {
            $masterwallet = MasterWallet::where('address', $from)->first();
            if ($masterwallet) {
                Log::info('🚫 Master wallet found. Webhook is a top-up and ignored.', ['address' => $from]);
                return;
            }
        }

        // Early exit if reference already exists
        if (isset($data['reference']) && WebhookResponse::where('reference', $data['reference'])->exists()) {
            Log::info('⛔ Duplicate reference found. Skipping webhook.', ['reference' => $data['reference']]);
            return;
        }

        if (!isset($data['accountId'])) {
            Log::warning('❌ accountId not found in webhook payload');
            return;
        }

        $account = VirtualAccount::where('account_id', $data['accountId'])->with('user')->first();

        if (!$account) {
            Log::warning('❌ Virtual account not found for accountId', ['accountId' => $data['accountId']]);
            return;
        }

        $userId = $account->user->id;
        $amount = $data['amount'];
        $reference = $data['reference'];
        $currency = $data['currency'];

        // Optional balance update (depending on your flow)
        $account->available_balance += $amount;
        $account->save();

        $exchangeRate = ExchangeFeeHelper::caclulateExchangeRate($amount, $currency);
        $amountUsd = $exchangeRate['amount_usd'];

        $webhook = WebhookResponse::create([
            'account_id'         => $data['accountId'],
            'subscription_type'  => $data['subscriptionType'],
            'amount'             => $amount,
            'reference'          => $reference,
            'currency'           => $currency,
            'tx_id'              => $data['txId'],
            'block_height'       => $data['blockHeight'],
            'block_hash'         => $data['blockHash'],
            'from_address'       => $data['from'] ?? 'not provided',
            'to_address'         => $data['to'],
            'transaction_date'   => Carbon::createFromTimestampMs($data['date']),
            'index'              => $data['index'],
        ]);

        // Lock to avoid duplicate job execution
        $lockKey = 'webhook_lock_' . $reference;
        $lock = Cache::lock($lockKey, 120); // 2 min lock

        if (!$lock->get()) {
            Log::warning("🔒 Webhook for reference $reference is already being processed.");
            return;
        }

        try {
            Log::info('💡 Virtual Account:', ['account' => $account]);

            $blockChain = strtolower($account->blockchain);
            $tx = null;

            if ($blockChain === 'ethereum') {
                $tx = $this->EthService->transferToMasterWallet($account, $amount);
            } elseif ($blockChain === 'bsc') {
                $tx = $this->BscService->transferToMasterWallet($account, $amount);
            } elseif ($blockChain === 'solana') {
                $tx = $this->SolanaService->transferToMasterWallet($account, $amount);
            } elseif ($blockChain === 'litecoin') {
                $tx = $this->LitecoinService->transferToMasterWallet($account, $amount);
            } elseif ($blockChain === 'tron') {
                $tx = $this->TronTransferService->transferTronToMasterWalletWithAutoFeeHandling($account, $amount);
            } elseif ($blockChain === 'bitcoin') {
                $tx = $this->BitcoinService->transferToMasterWallet($account, $amount);
            } else {
                Log::error('🚨 Unsupported blockchain:', ['blockchain' => $blockChain]);
            }
            Log::info('✅ Transfer to master wallet initiated', ['tx' => $tx]);
            $transaction = $this->transactionRepository->create(data: [
                'type' => 'receive',
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'network' => $account->blockchain,
                'reference' => $reference,
                'user_id' => $userId,
                'amount_usd' => $amountUsd,
                'transfer_type' => 'external',
            ]);

            ReceiveTransaction::create([
                'user_id'            => $userId,
                'virtual_account_id' => $account->id,
                'transaction_id'     => $transaction->id,
                'transaction_type'   => 'on_chain',
                'sender_address'     => $data['from'],
                'reference'          => $reference,
                'tx_id'              => $data['txId'],
                'amount'             => $amount,
                'currency'           => $currency,
                'blockchain'         => $account->blockchain,
                'amount_usd'         => $amountUsd,
                'status'             => 'completed',
            ]);
        } catch (\Exception $e) {
            FailedMasterTransfer::create([
                'virtual_account_id' => $account->id,
                'webhook_response_id' => $webhook->id,
                'reason' => $e->getMessage(),
            ]);

            Log::error('❌ Transfer to master wallet failed', ['error' => $e->getMessage()]);
        } finally {
            optional($lock)->release();
        }
    }
}
