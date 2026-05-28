<?php

namespace App\Repositories;

use App\Models\WithdrawRequest;
use App\Services\FiatBalanceService;
use App\Services\NotificationService;

// use App\Models\WithdrawRequest;

class WithdrawRequestRepository
{
    protected $withdrawTransactionRepository;
    public function __construct(WithdrawTransactionRepository $withdrawTransactionRepository)
    {
        $this->withdrawTransactionRepository = $withdrawTransactionRepository;
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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $currency = FiatBalanceService::normalizeCurrency($data);
            $fiat = app(FiatBalanceService::class);

            $data['balance_before'] = $fiat->deduct(
                (int) $data['user_id'],
                $currency,
                (string) $data['total']
            );

            $withdraw = WithdrawRequest::create($data);

            $symbol = $currency === 'ZAR' ? 'R' : '₦';
            app(NotificationService::class)->notifyUser(
                (int) $data['user_id'],
                'Withdrawal requested',
                "Your {$symbol}{$data['total']} {$currency} withdrawal is pending review.",
                'withdraw_pending'
            );

            return $withdraw;
        });
    }


    public function updateStatus($id, array $data)
    {
        // Use database transaction to ensure atomicity and prevent race conditions
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $data) {
            // Lock withdraw request to prevent concurrent status updates
            $withdraw = WithdrawRequest::where('id', $id)->lockForUpdate()->first();
        if (!$withdraw) {
            throw new \Exception('Withdraw Request not found');
        }
            
            // Check if already processed to prevent double processing
            if (isset($data['status'])) {
                if ($withdraw->status === $data['status']) {
                    return $withdraw; // Already in this status, don't process again
                }
            }
            
            $status = $data['status'] ?? null;
            
            // Update send_account if provided
            if (isset($data['send_account'])) {
                $withdraw->send_account = $data['send_account'];
            }
            
        if ($status == 'approved') {
            $withdraw->status = 'approved';
            $withdraw->save();
            $this->withdrawTransactionRepository->create([
                'withdraw_request_id' => $withdraw->id,
                'user_id' => $withdraw->user_id
            ]);
              
                app(NotificationService::class)->notifyUser(
                    (int) $withdraw->user_id,
                    'Withdrawal approved',
                    'Your withdrawal has been approved and is being processed.',
                    'withdraw_approved'
                );
        } elseif ($status == 'rejected') {
            $withdraw->status = 'rejected';

                $currency = FiatBalanceService::normalizeCurrency([
                    'currency' => $withdraw->currency ?? null,
                    'asset' => $withdraw->asset ?? null,
                ]);
                app(FiatBalanceService::class)->credit(
                    (int) $withdraw->user_id,
                    $currency,
                    (string) $withdraw->total
                );
                
            $withdraw->save();
            $this->withdrawTransactionRepository->create([
                'withdraw_request_id' => $withdraw->id,
                'user_id' => $withdraw->user_id
            ]);
                app(NotificationService::class)->notifyUser(
                    (int) $withdraw->user_id,
                    'Withdrawal rejected',
                    'Your withdrawal was rejected. The amount has been refunded to your account.',
                    'withdraw_rejected'
                );
        }
            return $withdraw->fresh();
        });
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    /**
     * Transform withdrawal request to include formatted bank_account object
     * Uses relationship if bank_account_id exists, otherwise uses direct fields
     * Returns array representation with formatted bank_account
     */
    private function transformWithdrawRequest($withdraw)
    {
        if (!$withdraw) {
            return null;
        }

        // Get formatted bank account
        $bankAccountData = $withdraw->getFormattedBankAccount();
        
        // Convert to array and transform
        $data = $withdraw->toArray();
        
        // Remove raw bank account fields from response
        unset($data['bank_account_id']);
        unset($data['bank_account_name']);
        unset($data['bank_account_code']);
        unset($data['account_name']);
        unset($data['account_number']);
        
        // Remove bankAccount relationship if present (we'll use formatted version)
        if (isset($data['bank_account'])) {
            unset($data['bank_account']);
        }
        
        // Add formatted bank_account object (always in consistent format)
        $data['bank_account'] = $bankAccountData;
        
        return $data;
    }

    public function getwithdrawRequestStatus($id)
    {
        $withdraw = WithdrawRequest::where('id', $id)->with('bankAccount')->first();
        if (!$withdraw) {
            return null;
        }
        return $this->transformWithdrawRequest($withdraw);
    }
    
    public function getWithDrawRequestByUserId($userId)
    {
        $withdraws = WithdrawRequest::where('user_id', $userId)
            ->with('bankAccount')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $withdraws->map(function ($withdraw) {
            $transformed = $this->transformWithdrawRequest($withdraw);
            $transformed['type'] = 'withdraw';
            return $transformed;
        });
    }
    
    public function findByTransactionId($transactionId)
    {
        // return WithdrawRequest::where('transaction_id', $transactionId)->first();
    }
    
    public function getAllwithdrawRequests()
    {
        $withdraws = WithdrawRequest::where('status', '!=', 'approved')
            ->with('bankAccount', 'user')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $withdraws->map(function ($withdraw) {
            // Transform to array first
            $transformed = $this->transformWithdrawRequest($withdraw);
            
            // Include user relationship if loaded
            if ($withdraw->relationLoaded('user') && $withdraw->user) {
                $transformed['user'] = $withdraw->user->toArray();
            }
            
            return $transformed;
        });
    }
}
