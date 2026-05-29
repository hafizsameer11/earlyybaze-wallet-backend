<?php

namespace App\Repositories\V3;

use App\Models\WithdrawRequest;
use App\Models\WithdrawTransaction;
use App\Repositories\transactionRepository;

/**
 * ZAR withdraw ledger entries — v3 only.
 */
class V3WithdrawTransactionRepository
{
    public function __construct(protected transactionRepository $transactionrepository) {}

    public function create(array $data): WithdrawTransaction
    {
        $withdrawRequestId = $data['withdraw_request_id'];
        $userId = $data['user_id'];
        $withdrawRequest = WithdrawRequest::where('id', $withdrawRequestId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $reference = 'EarlyBaze'.time();
        $transaction = $this->transactionrepository->create([
            'type' => 'withdrawTransaction',
            'amount' => $withdrawRequest->total,
            'user_id' => $withdrawRequest->user_id,
            'currency' => 'ZAR',
            'network' => 'ZAR',
            'reference' => $reference,
            'status' => $withdrawRequest->status,
            'amount_usd' => '',
        ]);

        if (! $transaction) {
            throw new \Exception('Failed to create transaction');
        }

        return WithdrawTransaction::create([
            'withdraw_request_id' => $withdrawRequestId,
            'transaction_id' => $transaction->id,
        ]);
    }
}
