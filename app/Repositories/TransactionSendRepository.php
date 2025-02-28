<?php

namespace App\Repositories;

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
        $currency = $data['currency'];
        $network = $data['network'];
        $email = $data['email'];
        $amount = $data['amount'];
        $receiver = User::where('email', $email)->first();
        if(!$receiver){
            throw new \Exception('Receiver not found');
        }
        $sender = Auth::user();
        $receiverAccount = VirtualAccount::where('user_id', $receiver->id)->where('currency', $currency)->where('blockchain', $network)->first();
        if (!$receiverAccount) {
            throw new \Exception('Receiver account not found');
        }
        $receiverAccountId = $receiverAccount->account_id;
        $senderAccount = VirtualAccount::where('user_id', $sender->id)->where('currency', $currency)->where('blockchain', $network)->first();
        if (!$senderAccount) {
            throw new \Exception('Sender account not found');
        }
        $senderAccountId = $senderAccount->account_id;
        $response = $this->tatumService->transferFunds($senderAccountId, $receiverAccountId, $amount, $currency);
        Log::info('Internal transfer response: ' . json_encode($response));
        if ($response['success'] == false) {
            throw new \Exception($response['error']);
        }
        return $response;
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
