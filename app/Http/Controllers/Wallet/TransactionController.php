<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuyTransactionReceiptRequest;
use App\Http\Requests\BuyTransactionRequest;
use App\Http\Requests\InternalTransferRequest;
use App\Http\Requests\OnChainTransaction;
use App\Http\Requests\SwapTransactionRequest;
use App\Models\DepositAddress;
use App\Models\ExchangeRate;
use App\Models\SwapTransaction;
use App\Models\Transaction;
use App\Models\TransactionSend;
use App\Models\UserActivity;
use App\Models\VirtualAccount;
use App\Services\BitcoinService;
use App\Services\BscService;
use App\Services\BuyTransactionService;
use App\Services\EthereumService;
use App\Services\LitecoinService;
use App\Services\SwapTransactionService;
use App\Services\TransactionSendService;
use App\Services\transactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $transactionSendService, $transactionService, $swapTransactionService, $buyTransactionService, $EthService, $LitecoinService, $BscService, $BitcoinService;

    public function __construct(TransactionSendService $transactionSendService, transactionService $transactionService, SwapTransactionService $swapTransactionService, BuyTransactionService $buyTransactionService, EthereumService $EthService, LitecoinService $LitecoinService, BscService $BscService, BitcoinService $BitcoinService)
    {
        $this->transactionSendService = $transactionSendService;
        $this->transactionService = $transactionService;
        $this->swapTransactionService = $swapTransactionService;
        $this->buyTransactionService = $buyTransactionService;
        $this->EthService = $EthService;
        $this->LitecoinService = $LitecoinService;
        $this->BscService = $BscService;
        $this->BitcoinService = $BitcoinService;
    }
    public function sendInternalTransaction(InternalTransferRequest $request)
    {
        try {
            Log::info("Internal Transfer request", $request->validated());

            $validated = $request->validated();
            $sendingType = filter_var($validated['email'], FILTER_VALIDATE_EMAIL) ? 'internal' : 'external';
            Log::info("Send data", $validated);
            Log::info("Detected Sending Type: $sendingType");
            if ($sendingType == 'internal') {

                $transaction = $this->transactionSendService->sendInternalTransaction(array_merge($validated, ['sending_type' => $sendingType]));
            } else {
                $user = Auth::user();
                $currency = strtoupper($validated['currency']);
                $network = strtolower($validated['network']);
                $userAccount=VirtualAccount::where('user_id')->where('currency',$currency)->first();
                // $depositAddress
                $deposiAddress=DepositAddress::where('virtual_account-id',$userAccount->id)->first();

                // Convert amount_after_fee (USD) to actual currency amount using exchange rate
                $exchangeRate = ExchangeRate::where('currency', $currency)->first();
                if (!$exchangeRate) {
                    throw new \Exception("Exchange rate not found for {$currency}");
                }

                $amountToSend = bcdiv($validated['fee_summary']['amount_after_fee'], $exchangeRate->rate_usd, 8);

                if ($network === 'ethereum') {
                    $transaction = $this->EthService->transferToExternalAddress($user, $validated['email'], $amountToSend, $currency);
                }

                if ($network === 'litecoin') {
                    $transaction = $this->LitecoinService->transferToExternalAddress($user, $validated['email'], $amountToSend);
                }
                if ($network === 'bsc') {
                    $transaction = $this->BscService->transferToExternalAddress($user, $validated['email'], $amountToSend, $currency);
                }
                if($network=='bitcoin'){
                    $transaction=$this->BitcoinService->transferToExternalAddress($user, $validated['email'], $amountToSend);
                }

                Log::info("Transaction created", [$transaction]);
                $senderTransaction = $this->transactionService->create([
                    'type' => 'send',
                    'amount' => $validated['amount'],
                    'currency' => $validated['currency'],
                    'status' => 'completed',
                    'network' => $validated['network'],
                    'reference' => $transaction['txHash'],
                    'user_id' => $user->id,
                    'amount_usd' => $validated['amount']
                ]);

                TransactionSend::create([
                    'transaction_type' => 'external',

                    'sender_address' =>  $deposiAddress->address ?? null,
                    'user_id' => $user->id,

                    'receiver_address' => $validated['email'],
                    'amount' => $validated['fee_summary']['amount_after_fee'],
                    'currency' => $validated['currency'],
                    'tx_id' => $transaction['txHash'],
                    'status' => 'completed',
                    'blockchain' => $validated['network'],
                    'transaction_id' => $senderTransaction->id,
                    'original_amount' => $validated['amount'],
                    'amount_after_fee' => $validated['fee_summary']['amount_after_fee'],
                    'platform_fee' => $validated['fee_summary']['platform_fee_usd'],
                    'network_fee' => $validated['fee_summary']['network_fee_usd'],
                    'fee_summary' => json_encode($validated['fee_summary']),
                    'fee_actual_transaction' => $transaction['fee']
                ]);

                // Record receiver transaction
                $transaction['refference'] = $transaction['txHash'];
                $transaction['amount'] = $validated['fee_summary']['amount_after_fee'];
                $transacton['currency'] = $validated['currency'];
                $transaction['transaction_id'] = $senderTransaction->id;
            }



            Log::info('Transaction Sendiind datya to backend', $transaction);
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            Log::error("Error in Internal Transfer: " . $e->getMessage());
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getSendTransactionforUser()
    {
        try {
            $user = Auth::user();
            $transactions = $this->transactionSendService->getTransactionforUser($user->id, 'user_id');
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getReceiveTransactionforUser()
    {
        try {
            $user = Auth::user();
            $transactions = $this->transactionSendService->getTransactionforUser($user->id, 'receiver_id');
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getTransactionsForUser()
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionService->getTransactionsForUser($user->id);
            return ResponseHelper::success($transaction, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getTransactionsForCurrency($currency)
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionService->getTransactionnsForUserWithCurrency($user->id, $currency);
            return ResponseHelper::success($transaction, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function sendOnChain(OnChainTransaction $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionSendService->sendOnChainTransaction($request->validated());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    //swap transaction
    public function swap(SwapTransactionRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->swapTransactionService->swap($request->validated());
            $userActivit=new UserActivity();
            $userActivit->user_id=$user->id;
            $data=$request->validated();
            $userActivit->content="You have successfully swapped {$data['amount']}{$data['currency']}";
            $userActivit->save();
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function singleSwapTransaction($id)
    {
        try {
            $user = Auth::user();
            $transaction = $this->swapTransactionService->singleSwapTransaction($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    //buy transactiuon
    public function buy(BuyTransactionRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->buyTransactionService->create($request->validated());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function attachSlip($id, BuyTransactionReceiptRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->buyTransactionService->attachSlip($id, $request->validated());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function singleBuyTransaction($id)
    {
        try {
            $user = Auth::user();
            $transaction = $this->buyTransactionService->find($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserAssetTransactions()
    {
        try {
            $user = Auth::user();
            $transactions = $this->buyTransactionService->getUserAssetTransactions($user->id);
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getPrivateKey($address)
    {
        $depositAddress = DepositAddress::where('address', $address)->first();
        $privateKey = $depositAddress->private_key;
        $privateKey =  Crypt::decryptString($privateKey);
        return $privateKey;
    }
}
