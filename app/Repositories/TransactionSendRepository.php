<?php

namespace App\Repositories;

use App\Models\DepositAddress;
use App\Models\TransactionSend;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ExchangeRateService;
use App\Services\TatumService;
use App\Services\transactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransactionSendRepository
{
    protected $tatumService, $transactionService, $exchangeRateService;
    public function __construct(TatumService $tatumService, transactionService $transactionService, ExchangeRateService $exchangeRateService)
    {
        $this->tatumService = $tatumService;
        $this->transactionService = $transactionService;
        $this->exchangeRateService = $exchangeRateService;
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
        $transaction = TransactionSend::find($id);
        if (!$transaction) {
            throw new \Exception('Transaction not found');
        }
        return $transaction;
    }

    public function create(array $data)
    {
        // Add logic to create data
    }
    public function sendInternalTransaction(array $data)
    {
        try {
            // Extract request parameters
            $currency = $data['currency'];
            $network = $data['network'];
            $email = $data['email'];
            $amount = $data['amount'];

            // Find Receiver
            $receiver = User::where('email', $email)->first();
            if (!$receiver) {
                return ['success' => false, 'error' => 'Receiver not found'];
            }
            $sender = Auth::user();
            $receiverAccount = VirtualAccount::where('user_id', $receiver->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$receiverAccount) {
                return ['success' => false, 'error' => 'Receiver account not found'];
            }
            $receiverDepositAddress = DepositAddress::where('virtual_account_id', $receiverAccount->id)->first();
            $senderAccount = VirtualAccount::where('user_id', $sender->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();
            if (!$senderAccount) {
                return ['success' => false, 'error' => 'Sender account not found'];
            }
            $receiverAccountId = $receiverAccount->account_id;
            $senderAccountId = $senderAccount->account_id;
            $response = $this->tatumService->transferFunds($senderAccountId, $receiverAccountId, $amount, $currency);
            Log::info('Internal transfer response: ' . json_encode($response));
            $status = 'failed';
            $txId = null;
            $errorMessage = null;
            if (isset($response['reference'])) {
                $status = 'completed'; // Success case
                $txId = $response['reference'];
                $senderAccount->available_balance -= $amount;
                $senderAccount->account_balance -= $amount;
                $senderAccount->save();
                $receiverAccount->available_balance += $amount;
                $receiverAccount->account_balance += $amount;
                $receiverAccount->save();
            } elseif (isset($response['errorCode']) && $response['errorCode'] === "balance.insufficient") {
                $status = 'failed';
                $errorMessage = "Insufficient balance: " . $response['message'];
            }
            $exchangerate = $this->exchangeRateService->getByCurrency($currency);
            //exchange rate can throw error if not found so handle it
            $amount_usd = null;
            if ($exchangerate) {
                $amount_usd = $amount * $exchangerate->rate_usd;
            }
            $transcation = $this->transactionService->create([
                'type' => 'send',
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'network' => $network,
                'reference' => $txId,
                'user_id' => $sender->id,
                'amount_usd' => $amount_usd
            ]);
            TransactionSend::create([
                'transaction_type' => 'internal',
                'sender_virtual_account_id' => $senderAccountId,
                'receiver_virtual_account_id' => $receiverAccountId,
                'sender_address' => null,
                'user_id' => $sender->id ?? null,
                'receiver_id' => $receiver->id ?? null,
                'receiver_address' => $receiverDepositAddress,
                'amount' => $amount,
                'currency' => $currency,
                'tx_id' => $txId,
                'block_height' => null,
                'block_hash' => null,
                'gas_fee' => null,
                'status' => $status,
                'blockchain' => $network,
                'transaction_id' => $transcation->id
            ]);
            //create transactionsend and transaction for receiver too

            $transcation = $this->transactionService->create([
                'type' => 'receive',
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'network' => $network,
                'reference' => $txId,
                'user_id' => $receiver->id,
                'amount_usd' => $amount_usd
            ]);
            TransactionSend::create([
                'transaction_type' => 'internal',
                'sender_virtual_account_id' => $senderAccountId,
                'receiver_virtual_account_id' => $receiverAccountId,
                'sender_address' => null,
                'user_id' => $sender->id ?? null,
                'receiver_id' => $receiver->id ?? null,
                'receiver_address' => $receiverDepositAddress,
                'amount' => $amount,
                'currency' => $currency,
                'tx_id' => $txId,
                'block_height' => null,
                'block_hash' => null,
                'gas_fee' => null,
                'status' => $status,
                'blockchain' => $network,
                'transaction_id' => $transcation->id
            ]);

            // Return Data for Controller
            return $response;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
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
