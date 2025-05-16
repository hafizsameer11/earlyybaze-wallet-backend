<?php

namespace App\Repositories;

use App\Models\DepositAddress;
use App\Models\ReceiveTransaction;
use App\Models\TransactionSend;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Services\ExchangeRateService;
use App\Services\NotificationService;
use App\Services\TatumService;
use App\Services\transactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
// use Str;
use Illuminate\Support\Str;

class TransactionSendRepository
{
    protected $tatumService, $transactionService, $exchangeRateService, $notificationService;
    public function __construct(TatumService $tatumService, transactionService $transactionService, ExchangeRateService $exchangeRateService, NotificationService $notificationService)
    {
        $this->tatumService = $tatumService;
        $this->transactionService = $transactionService;
        $this->exchangeRateService = $exchangeRateService;
        $this->notificationService = $notificationService;
    }
    public function getTransactionforUser($user_id, $userType)
    {

        return TransactionSend::where($userType, $user_id)->get();
    }
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        $transaction = TransactionSend::with('transaction')->find($id);
        if (!$transaction) {
            throw new \Exception('Transaction not found');
        }
        return $transaction;
    }
    public function findByTransactionId($transactionId, $type = "send")
    {
        if ($type === "send") {
            $transaction = TransactionSend::where('transaction_id', $transactionId)
                ->with('transaction') // Assuming `transaction()` is the polymorphic relationship
                ->first();

            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            $walletCurrency = WalletCurrency::where('currency', $transaction->currency)->first();

            return [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'transaction_type' => $transaction->transaction_type,
                'currency' => $transaction->currency,
                'symbol' => $walletCurrency->symbol ?? 'default.png',
                'tx_id' => $transaction->tx_id,
                'block_hash' => $transaction->block_hash,
                'gas_fee' => $transaction->gas_fee,
                'receiver_address' => $transaction->receiver_virtual_account_id,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'amount_usd' => $transaction->amount_usd ?? '0.00',
                'created_at' => $transaction->created_at,
                'sender_address' => $transaction->sender_address,
            ];
        }

        // Handle receive transaction
        $receiveTransaction = \App\Models\ReceiveTransaction::where('transaction_id', $transactionId)
            ->with('transaction') // If you created a relation, else skip this
            ->first();

        if (!$receiveTransaction) {
            throw new \Exception('Receive transaction not found');
        }

        $walletCurrency = WalletCurrency::where('currency', $receiveTransaction->currency)->first();

        return [
            'id' => $receiveTransaction->id,
            'transaction_id' => $receiveTransaction->transaction_id,
            'transaction_type' => $receiveTransaction->transaction_type,
            'currency' => $receiveTransaction->currency,
            'symbol' => $walletCurrency->symbol ?? 'default.png',
            'tx_id' => $receiveTransaction->tx_id,
            'block_hash' => null, // Optional: You can include if you're saving it in your model
            'gas_fee' => null, // Optional: Include actual gas if recorded
            'sender_address' => $receiveTransaction->sender_address,
            'status' => $receiveTransaction->status,
            'amount' => $receiveTransaction->amount,
            'amount_usd' => $receiveTransaction->amount_usd,
            'created_at' => $receiveTransaction->created_at,
        ];
    }


    public function create(array $data)
    {
        // Add logic to create data
    }
    public function sendInternalTransaction(array $data)
    {
        try {
            $currency = $data['currency'];
            $network = $data['network'];
            $email = $data['email'];
            $amount = $data['amount'];
            $sendingType = $data['sending_type'];
            $receiver = User::where('email', $email)->first();
            if (!$receiver) {
                return ['success' => false, 'error' => 'Receiver not found'];
            }

            $sender = Auth::user();

            $senderAccount = VirtualAccount::where('user_id', $sender->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            $receiverAccount = VirtualAccount::where('user_id', $receiver->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$senderAccount || !$receiverAccount) {
                return ['success' => false, 'error' => 'Sender or receiver account not found'];
            }

            if ($senderAccount->available_balance < $amount) {
                return ['success' => false, 'error' => 'Insufficient balance'];
            }

            $receiverDepositAddress = DepositAddress::where('virtual_account_id', $receiverAccount->id)->first();

            // Adjust balances
            $senderAccount->available_balance -= $amount;
            $senderAccount->account_balance -= $amount;
            $senderAccount->save();

            $receiverAccount->available_balance += $amount;
            $receiverAccount->account_balance += $amount;
            $receiverAccount->save();

            // Calculate USD equivalent
            $exchangerate = $this->exchangeRateService->getByCurrency($currency);
            $amountUsd = $exchangerate ? $amount * $exchangerate->rate_usd : null;

            // Generate a reference
            $reference = strtoupper(Str::random(16));

            // Record sender transaction
            $senderTransaction = $this->transactionService->create([
                'type' => 'send',
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'network' => $network,
                'reference' => $reference,
                'user_id' => $sender->id,
                'amount_usd' => $amountUsd
            ]);
            $senderNotification = $this->notificationService->sendToUserById($sender->id, "Internal Send", "You have sent $amount $currency");
            UserNotification::create([
                'user_id' => $sender->id,
                'title' => 'Internal Send',
                'message' => "You have sent $amount $currency"
            ]);
            TransactionSend::create([
                'transaction_type' => 'internal',
                'sender_virtual_account_id' => $senderAccount->account_id,
                'receiver_virtual_account_id' => $receiverAccount->account_id,
                'sender_address' => $sender->email ?? null,
                'user_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'receiver_address' => $receiverDepositAddress->address ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'tx_id' => $reference,
                'status' => 'completed',
                'blockchain' => $network,
                'transaction_id' => $senderTransaction->id,
                'amount_usd' => $amountUsd,
                'network_fee' => 0,
            ]);

            // Record receiver transaction
            $receiverTransaction = $this->transactionService->create([
                'type' => 'receive',
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'network' => $network,
                'reference' => $reference,
                'user_id' => $receiver->id,
                'amount_usd' => $amountUsd,
            ]);

            $noitifcation = $this->notificationService->sendToUserById($receiver->id, "Internal Receive", "You have received $amount $currency");
            // ⬇️ Replace 2nd TransactionSend with ReceiveTransaction
            ReceiveTransaction::create([
                'user_id'            => $receiver->id,
                'virtual_account_id' => $receiverAccount->id,
                'transaction_id'     => $receiverTransaction->id,
                'transaction_type'   => 'internal',
                'sender_address'     => $senderAccount->address,
                'reference'          => $reference,
                'tx_id'              => $reference,
                'amount'             => $amount,
                'currency'           => $currency,
                'blockchain'         => $receiverAccount->blockchain,
                'amount_usd'         => $amountUsd,
                'status'             => 'completed',
            ]);


            return [
                'success' => true,
                'transaction_id' => $senderTransaction->id,
                'reference' => $reference,
            ];
        } catch (\Exception $e) {
            Log::error('Internal Transfer Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendOnChainTransaction(array $data)
    {
        try {
            // Extract transaction details
            $currency = strtoupper($data['currency']); // Example: ETH, USDT
            $network = strtoupper($data['network']); // Blockchain: ETH, BSC, TRON, etc.
            $amount = $data['amount'];
            $receiverAddress = $data['address'];

            // Get sender's virtual account
            $sender = Auth::user();
            $senderAccount = VirtualAccount::where('user_id', $sender->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$senderAccount) {
                return ['success' => false, 'error' => 'Sender account not found'];
            }

            // Get sender's deposit address
            $senderDepositAddress = DepositAddress::where('virtual_account_id', $senderAccount->id)->first();
            if (!$senderDepositAddress) {
                return ['success' => false, 'error' => 'Sender deposit address not found'];
            }
            $senderAddress = $senderDepositAddress->address;

            // Estimate Gas Fee
            $gasFeeResponse = $this->tatumService->estimateGasFee($network, $senderAddress, $receiverAddress, $amount);
            if (!isset($gasFeeResponse['gasLimit']) || !isset($gasFeeResponse['gasPrice'])) {
                return ['success' => false, 'error' => 'Failed to estimate gas fee'];
            }

            $gasLimit = $gasFeeResponse['gasLimit'];
            $gasPrice = $gasFeeResponse['gasPrice'];
            $gasFee = $gasLimit * $gasPrice; // Total gas cost

            // Ensure sender has enough balance for transaction + gas fee
            $totalCost = $amount + $gasFee;
            if ($senderAccount->available_balance < $totalCost) {
                return ['success' => false, 'error' => 'Insufficient balance to cover amount + gas fee'];
            }

            // Execute On-Chain Transaction
            $response = $this->tatumService->sendBlockchainTransaction([
                "chain" => $network,
                "from" => $senderAddress,
                "to" => $receiverAddress,
                "amount" => (string) $amount,
                "gasLimit" => $gasLimit,
                "gasPrice" => $gasPrice
            ]);

            Log::info('On-Chain Transfer Response: ' . json_encode($response));

            $status = 'failed';
            $txId = null;
            if (isset($response['txId'])) {
                $status = 'pending';
                $txId = $response['txId'];
            } elseif (isset($response['errorCode'])) {
                throw new \Exception('Failed to send on-chain transaction: ' . $response['message']);
            }
            // $transaction=

            // Store transaction details
            TransactionSend::create([
                'transaction_type' => 'on_chain',
                'sender_virtual_account_id' => $senderAccount->account_id,
                'receiver_virtual_account_id' => null,
                'sender_address' => $senderAddress,
                'receiver_address' => $receiverAddress,
                'amount' => $amount,
                'currency' => $currency,
                'tx_id' => $txId,
                'gas_fee' => $gasFee,
                'status' => $status,
                'blockchain' => $network,
            ]);

            // Deduct balance
            $senderAccount->available_balance -= $totalCost;
            $senderAccount->account_balance -= $totalCost;
            $senderAccount->save();

            return ['success' => true, 'transaction_id' => $txId, 'status' => $status];
        } catch (\Exception $e) {
            Log::error('On-Chain Transfer Error: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
