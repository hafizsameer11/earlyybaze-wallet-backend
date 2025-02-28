<?php

namespace App\Repositories;

use App\Models\TransactionSend;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\TatumService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransactionSendRepository
{
    protected $tatumService;
    public function __construct(TatumService $tatumService)
    {
        $this->tatumService = $tatumService;
    }
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
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

            // Find Sender
            $sender = Auth::user();

            // Get Receiver's Virtual Account
            $receiverAccount = VirtualAccount::where('user_id', $receiver->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$receiverAccount) {
                return ['success' => false, 'error' => 'Receiver account not found'];
            }

            // Get Sender's Virtual Account
            $senderAccount = VirtualAccount::where('user_id', $sender->id)
                ->where('currency', $currency)
                ->where('blockchain', $network)
                ->first();

            if (!$senderAccount) {
                return ['success' => false, 'error' => 'Sender account not found'];
            }

            // Store IDs
            $receiverAccountId = $receiverAccount->account_id;
            $senderAccountId = $senderAccount->account_id;

            // Send Transfer Request to Tatum
            $response = $this->tatumService->transferFunds($senderAccountId, $receiverAccountId, $amount, $currency);
            Log::info('Internal transfer response: ' . json_encode($response));

            // Determine Transaction Status
            $status = 'failed';
            $txId = null;
            $errorMessage = null;

            if (isset($response['reference'])) {
                $status = 'completed'; // Success case
                $txId = $response['reference'];

                // Update Sender's Balance
                $senderAccount->available_balance -= $amount;
                $senderAccount->account_balance -= $amount;
                $senderAccount->save();

                // Update Receiver's Balance
                $receiverAccount->available_balance += $amount;
                $receiverAccount->account_balance += $amount;
                $receiverAccount->save();
            } elseif (isset($response['errorCode']) && $response['errorCode'] === "balance.insufficient") {
                $status = 'failed';
                $errorMessage = "Insufficient balance: " . $response['message'];
            }

            // Store Transaction Details
            TransactionSend::create([
                'transaction_type' => 'internal',
                'sender_virtual_account_id' => $senderAccountId,
                'receiver_virtual_account_id' => $receiverAccountId,
                'sender_address' => null,
                'receiver_address' => null,
                'amount' => $amount,
                'currency' => $currency,
                'tx_id' => $txId,
                'block_height' => null,
                'block_hash' => null,
                'gas_fee' => null,
                'status' => $status,
                'blockchain' => $network,
            ]);

            // Return Data for Controller
            return $response;
        } catch (\Exception $e) {
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
