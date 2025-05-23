<?php

namespace App\Http\Controllers;

use App\Helpers\BlockChainHelper;
use App\Helpers\ExchangeFeeHelper;
use App\Jobs\ProcessBlockchainWebhook;
use App\Models\FailedMasterTransfer;
use App\Models\MasterWallet;
use App\Models\ReceiveTransaction;
use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
use App\Repositories\transactionRepository;
use App\Services\EthereumService;
use App\Services\transactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{

    protected $transactionRepository, $EthService, $TronService;
    public function __construct(transactionRepository $transactionRepository, EthereumService $EthService)
    {

        $this->transactionRepository = $transactionRepository;
        $this->EthService = $EthService;
    }


    public function webhook(Request $request)
    {
        // Log::info($request->all());
        // $from = $request->from;
        // $masterwallet = MasterWallet::where('address', $from)->first();
        // if ($masterwallet) {
        //     Log::info('Master wallet found webhook  is ignored because that is a topup', ['address' => $from]);
        //     return response()->json(['message' => 'Webhook received'], 200);
        // }
        // // Early exit if reference already exists
        // if (WebhookResponse::where('reference', $request->reference)->exists()) {
        //     return response()->json(['message' => 'Duplicate reference. Webhook ignored.'], 200);
        // }

        // if (!$request->has('accountId')) {
        //     return response()->json(['message' => 'Account ID is required'], 200);
        // }

        // $account = VirtualAccount::where('account_id', $request->accountId)->with('user')->first();

        // if (!$account) {
        //     return response()->json(['message' => 'Virtual account not found'], 200);
        // }

        // // Update account balance
        // $account->available_balance += $request->amount;
        // // $account->save();
        // $userId = $account->user->id;
        // $exchangeRate = ExchangeFeeHelper::caclulateExchangeRate($request->amount, $account->currency);
        // $amountUsd = $exchangeRate['amount_usd'];
        // // Log webhook response
        // $webhook =  WebhookResponse::create([
        //     'account_id'         => $request->accountId,
        //     'subscription_type'  => $request->subscriptionType,
        //     'amount'             => $request->amount,
        //     'reference'          => $request->reference,
        //     'currency'           => $request->currency,
        //     'tx_id'              => $request->txId,
        //     'block_height'       => $request->blockHeight,
        //     'block_hash'         => $request->blockHash,
        //     'from_address'       => $request->from,
        //     'to_address'         => $request->to,
        //     'transaction_date'   => Carbon::createFromTimestampMs($request->date),
        //     'index'              => $request->index,
        // ]);

        // $lockKey = 'webhook_lock_' . $request->reference;

        // $lock = Cache::lock($lockKey, 30); // 30 seconds

        // if (!$lock->get()) {
        //     // Another process is already handling this webhook
        //     return response()->json(['message' => 'Webhook already processing.'], 200);
        // }
        // // Trigger transfer to master wallet
        // try {
        //     Log::info("cirtual account", ['account' => $account]);
        //     $blockChain = $account->blockchain;
        //     $tx = null;
        //     if (strtolower($blockChain) == 'ethereum') {
        //         $tx =  $this->EthService->transferToMasterWallet($account, $request->amount);
        //     }
        //     Log::info('Transfer to master wallet initiated', ['tx' => $tx]);
        //     $transcation = $this->transactionRepository->create(data: [
        //         'type' => 'receive',
        //         'amount' => $request->amount,
        //         'currency' => $account->currency,
        //         'status' => 'completed',
        //         'network' => $account->blockchain,
        //         'reference' => $request->reference,
        //         'user_id' => $userId,
        //         'amount_usd' => $amountUsd,
        //         'transfer_type' => 'external',
        //     ]);
        //     ReceiveTransaction::create([
        //         'user_id'            => $userId,
        //         'virtual_account_id' => $account->id,
        //         'transaction_id'     => $transcation->id,
        //         'transaction_type'   => 'on_chain',
        //         'sender_address'     => $request->from,
        //         'reference'          => $request->reference,
        //         'tx_id'              => $request->txId,
        //         'amount'             => $request->amount,
        //         'currency'           => $account->currency,
        //         'blockchain'         => $account->blockchain,
        //         'amount_usd'         => $amountUsd,
        //         'status'             => 'completed',
        //     ]);
        // } catch (\Exception $e) {
        //     $failedMasterTransfer = FailedMasterTransfer::create([
        //         'virtual_account_id' => $account->id,
        //         'webhook_response_id' => $webhook->id,
        //         'reason' => $e->getMessage(),
        //     ]);
        //     Log::error('Failed to dispatch transfer to master wallet: ' . $e->getMessage());
        //     return response()->json(['message' => 'Webhook received'], 200);
        // }

        // return response()->json(['message' => 'Webhook received'], 200);

        Log::info('🚀 Incoming Webhook Request', $request->all());

        ProcessBlockchainWebhook::dispatch($request->all());

        return response()->json(['message' => 'Webhook queued for processing'], 200);
    }
}
