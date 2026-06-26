<?php

namespace App\Jobs;

use App\Helpers\ExchangeFeeHelper;
use App\Models\DepositAddress;
use App\Models\FailedMasterTransfer;
use App\Support\AllowedFungibleContracts;
use App\Models\MasterWallet;
use App\Models\ReceivedAsset;
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
use App\Services\NotificationService;
use App\Services\SolanaService;
use App\Services\RejectedDepositWebhookRecorder;
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

    protected $transactionRepository,$notificationService;
    protected $EthService, $BscService, $BitcoinService, $SolanaService, $LitecoinService, $TronTransferService;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(transactionRepository $transactionRepository, EthereumService $EthService, TronTransferService $TronTransferService, BscService $BscService, BitcoinService $BitcoinService, SolanaService $SolanaService, LitecoinService $LitecoinService, NotificationService $notificationService)
    {
        $this->transactionRepository = $transactionRepository;
        $this->EthService = $EthService;
        $this->BscService = $BscService;
        $this->BitcoinService = $BitcoinService;
        $this->SolanaService = $SolanaService;
        $this->LitecoinService = $LitecoinService;
        $this->TronTransferService = $TronTransferService;
        $this->notificationService = $notificationService;


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

        $txId = (string) ($data['txId'] ?? '');
        if ($this->isDuplicateOnChainDeposit($txId, $data)) {
            Log::info('v1 webhook ignored: duplicate tx_id/logIndex already credited', [
                'txId' => $txId,
                'logIndex' => AllowedFungibleContracts::payloadLogIndex($data),
            ]);

            return;
        }

        if (!isset($data['accountId'])) {
            Log::warning('❌ accountId not found in webhook payload');
            return;
        }

        $account = VirtualAccount::where('account_id', $data['accountId'])->with('user')->first();

        if (! $account) {
            Log::warning('❌ Virtual account not found for accountId', ['accountId' => $data['accountId']]);
            return;
        }

        if (AllowedFungibleContracts::isFungiblePayload($data)) {
            $contract = AllowedFungibleContracts::payloadContract($data);
            if (! AllowedFungibleContracts::isAllowed($contract)) {
                RejectedDepositWebhookRecorder::record(
                    'v1',
                    AllowedFungibleContracts::REJECT_NON_ALLOWLISTED_CONTRACT,
                    $data,
                    $account,
                    $data['reference'] ?? null
                );
                Log::info('v1 webhook ignored: fungible tx with non-allowlisted contract', [
                    'accountId' => $data['accountId'],
                    'txId' => $txId,
                    'contractAddress' => $contract,
                    'currency' => $data['currency'] ?? null,
                    'symbol' => $data['tokenMetadata']['symbol'] ?? null,
                ]);

                return;
            }
        }

        $fungibleReject = AllowedFungibleContracts::rejectReasonForFungibleDeposit($account, $data);
        if ($fungibleReject !== null) {
            RejectedDepositWebhookRecorder::record(
                'v1',
                $fungibleReject,
                $data,
                $account,
                $data['reference'] ?? null
            );
            Log::info('v1 webhook ignored: '.AllowedFungibleContracts::rejectionReasonLabel($fungibleReject), [
                'accountId' => $data['accountId'],
                'txId' => $txId,
                'reason' => $fungibleReject,
                'account_currency' => $account->currency,
                'contractAddress' => AllowedFungibleContracts::payloadContract($data),
                'payload_currency' => $data['currency'] ?? null,
            ]);

            return;
        }

        $userId = $account->user->id;
        $amount = (string) ($data['amount'] ?? $data['value'] ?? '');
        if ($amount === '' || bccomp($amount, '0', 8) <= 0) {
            Log::warning('v1 webhook ignored: missing or zero amount', ['accountId' => $data['accountId'], 'txId' => $txId]);

            return;
        }

        if (! isset($data['reference']) || $data['reference'] === '') {
            Log::warning('v1 webhook ignored: missing reference', ['accountId' => $data['accountId'], 'txId' => $txId]);

            return;
        }

        $reference = $data['reference'];
        $currency = strtoupper((string) $account->currency);

        // Lock to avoid duplicate job execution (BEFORE any processing)
        $lockKey = 'webhook_lock_' . $reference;
        $lock = Cache::lock($lockKey, 120); // 2 min lock

        if (!$lock->get()) {
            Log::warning("🔒 Webhook for reference $reference is already being processed.");
            return;
        }

        try {
            $logIndex = AllowedFungibleContracts::payloadLogIndex($data);
            $txMs = $data['date'] ?? $data['txTimestamp'] ?? $data['blockTimestamp'] ?? null;
            $transactionDate = is_numeric($txMs)
                ? Carbon::createFromTimestampMs((int) $txMs)
                : now();

            $receivedAsset = null;
            $deposit = DepositAddress::query()
                ->where('virtual_account_id', $account->id)
                ->when(! empty($data['to']), fn ($q) => $q->whereRaw('LOWER(address) = ?', [strtolower((string) $data['to'])]))
                ->first();

            \Illuminate\Support\Facades\DB::transaction(function () use (
                $data, $account, $userId, $amount, $reference, $currency, $logIndex, $transactionDate, &$receivedAsset
            ) {
                if (ReceivedAsset::where('reference', $reference)->exists()) {
                    Log::info('⛔ Duplicate reference found in transaction. Skipping webhook.', ['reference' => $reference]);

                    return;
                }

                $receivedAsset = ReceivedAsset::create([
                    'account_id' => $data['accountId'],
                    'subscription_type' => $data['subscriptionType'] ?? null,
                    'amount' => $amount,
                    'reference' => $reference,
                    'currency' => $currency,
                    'tx_id' => $data['txId'],
                    'from_address' => $data['from'] ?? 'not provided',
                    'to_address' => $data['to'] ?? null,
                    'transaction_date' => $transactionDate,
                    'status' => 'processing',
                    'verification_status' => 'pending',
                    'index' => $logIndex,
                    'user_id' => $userId,
                ]);
            });

            if ($receivedAsset) {
                VerifyDepositWebhookJob::dispatch(
                    $receivedAsset->id,
                    $data,
                    $reference,
                    $deposit?->id,
                    $account->id,
                );
            }
        } catch (\Exception $e) {
            $webhookResponse = WebhookResponse::where('reference', $reference)->first();

            FailedMasterTransfer::create([
                'virtual_account_id' => $account->id,
                'webhook_response_id' => $webhookResponse ? $webhookResponse->id : null,
                'reason' => $e->getMessage(),
            ]);

            Log::error('❌ Webhook processing failed', ['error' => $e->getMessage()]);
        } finally {
            optional($lock)->release();
        }
    }

    private function isDuplicateOnChainDeposit(string $txId, array $data): bool
    {
        if ($txId === '') {
            return false;
        }

        $q = ReceivedAsset::query()->where('tx_id', $txId);
        $logIndex = AllowedFungibleContracts::payloadLogIndex($data);
        if ($logIndex !== null) {
            $q->where('index', $logIndex);
        }

        return $q->exists();
    }
}
