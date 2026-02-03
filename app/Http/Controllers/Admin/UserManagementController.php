<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccountRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\BankAccountService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    protected $userService, $bankAccountService,$userRepo;
    public function __construct(UserService $userService, BankAccountService $bankAccountService,UserRepository $userRepository)
    {
        $this->userService = $userService;
        $this->userRepo = $userRepository;
        $this->bankAccountService = $bankAccountService;
    }



    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user) {
            // Append timestamp to avoid unique constraint issues
            $timestamp = now()->timestamp;

            // Update email before soft-deleting
            $user->email = $user->email . '-deleted-' . $timestamp;
            $user->save();

            // Perform soft delete
            $user->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'User soft deleted and email updated',
        ]);
    }
    public function blockUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active; // Toggle the value
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => $user->is_active ? 'User unblocked' : 'User blocked',
        ]);
    }
        public function getUserManagementData()
    {
        try {
            $data = $this->userService->getUserManagementData();
            return ResponseHelper::success($data, 'User details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserAssets($userId)
    {
        try {
            $data = $this->userRepo->getUserAssets($userId);
            return ResponseHelper::success($data, 'User assets fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserDetails($userId)
    {
        try {
            $data = $this->userService->userDetails($userId);
            return ResponseHelper::success($data, 'User details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getBanksForUser($userId)
    {
        try {
            if (!$userId) {
                return ResponseHelper::error('User id is required', 500);
            }
            $data = $this->bankAccountService->getforUser($userId);
            return ResponseHelper::success($data, 'Bank details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function adminVirtualAccounts()
    {
        try {
            $user = User::where('role', 'admin')->first();
            $data = $this->userService->getUserVirtualAccounts($user->id);
            return ResponseHelper::success($data, 'User virtual accounts fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserVirtualAccounts($userId)
    {
        try {
            $data = $this->userService->getUserVirtualAccounts($userId);
            return ResponseHelper::success($data, 'User virtual accounts fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getNonUsers()
    {
        try {
            $data = $this->userService->getNonUsers();
            return ResponseHelper::success($data, 'Non users fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function createUser(RegisterRequest $request)
    {
        try {
            $user = $this->userService->createUser($request->validated());
            // $user;
            return ResponseHelper::success($user, 'User created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function createBankAccount(BankAccountRequest $request, $userId)
    {
        try {
            $data = $this->bankAccountService->createBankAccount($request->validated(), $userId);
            return ResponseHelper::success($data, 'Bank account created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserBalances()
    {
        try {
            $data = $this->userService->getUserBalances();
            return ResponseHelper::success($data, 'User balances fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getBalanceByCurrency($currencyId)
    {
        try {
            $data = $this->userService->getBalanceByCurrency($currencyId);
            return ResponseHelper::success($data, 'User balances fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function deactivateUser($userId){
        try {
            $data = $this->userService->deactivateUser($userId);
            return ResponseHelper::success($data, 'User deactivated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get complete user details including all transactions, withdraw requests, and assets
     * Bypasses soft delete scopes to include deleted records
     */
    public function getCompleteUserDetails($userId)
    {
        try {
            // Get user with trashed (bypass soft delete scope)
            $user = User::withTrashed()->find($userId);
            
            if (!$user) {
                return ResponseHelper::error('User not found', 404);
            }

            // Get all withdraw requests (including soft deleted)
            $withdrawRequests = \App\Models\WithdrawRequest::withTrashed()
                ->where('user_id', $userId)
                ->with(['bankAccount' => function($query) {
                    $query->withTrashed();
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get user account (naira balance)
            $userAccount = \App\Models\UserAccount::where('user_id', $userId)->first();

            // Get all bank accounts (including soft deleted)
            $bankAccounts = \App\Models\BankAccount::withTrashed()
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get KYC information
            $kyc = \App\Models\Kyc::where('user_id', $userId)->first();

            // Get user activity
            $userActivity = \App\Models\UserActivity::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get all receive transactions with sender details
            $receiveTransactions = \App\Models\ReceiveTransaction::where('user_id', $userId)
                ->with([
                    'transaction',
                    'virtualAccount.walletCurrency',
                    'user' => function($query) {
                        $query->withTrashed();
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($receive) {
                    // Get sender details if it's an internal transaction
                    $sender = null;
                    if ($receive->transaction_type === 'internal' && $receive->sender_address) {
                        // Method 1: Find corresponding TransactionSend record by reference (most reliable)
                        $sendTransaction = \App\Models\TransactionSend::where('tx_id', $receive->reference)
                            ->orWhere('tx_id', $receive->tx_id)
                            ->with(['user' => function($q) {
                                $q->withTrashed();
                            }])
                            ->first();
                        
                        if ($sendTransaction && $sendTransaction->user) {
                            $sender = $sendTransaction->user;
                        } else {
                            // Method 2: Find sender by deposit address (sender_address is deposit address for internal)
                            $depositAddress = \App\Models\DepositAddress::where('address', $receive->sender_address)
                                ->with(['virtualAccount.user' => function($q) {
                                    $q->withTrashed();
                                }])
                                ->first();
                            
                            if ($depositAddress && $depositAddress->virtualAccount && $depositAddress->virtualAccount->user) {
                                $sender = $depositAddress->virtualAccount->user;
                            } else {
                                // Method 3: Try to find by email (fallback)
                                $sender = \App\Models\User::withTrashed()
                                    ->where('email', $receive->sender_address)
                                    ->first();
                                
                                // Method 4: Try to find by virtual account ID
                                if (!$sender) {
                                    $sender = \App\Models\User::withTrashed()
                                        ->whereHas('virtualAccounts', function($q) use ($receive) {
                                            $q->where('account_id', $receive->sender_address);
                                        })
                                        ->first();
                                }
                            }
                        }
                    }
                    
                    return [
                        'id' => $receive->id,
                        'user_id' => $receive->user_id,
                        'transaction_id' => $receive->transaction_id,
                        'transaction_type' => $receive->transaction_type,
                        'sender_address' => $receive->sender_address,
                        'reference' => $receive->reference,
                        'tx_id' => $receive->tx_id,
                        'amount' => $receive->amount,
                        'currency' => $receive->currency,
                        'blockchain' => $receive->blockchain,
                        'amount_usd' => $receive->amount_usd,
                        'status' => $receive->status,
                        'balance_before' => $receive->balance_before,
                        'created_at' => $receive->created_at,
                        'updated_at' => $receive->updated_at,
                        'transaction' => $receive->transaction,
                        'virtual_account' => $receive->virtualAccount,
                        'sender' => $sender ? [
                            'id' => $sender->id,
                            'name' => $sender->name,
                            'fullName' => $sender->fullName,
                            'email' => $sender->email,
                            'phone' => $sender->phone,
                            'is_deleted' => $sender->deleted_at !== null,
                            'deleted_at' => $sender->deleted_at,
                        ] : null,
                    ];
                });

            // Get all send transactions with receiver details
            $sendTransactions = \App\Models\TransactionSend::where('user_id', $userId)
                ->with([
                    'transaction',
                    'receiver' => function($query) {
                        $query->withTrashed();
                    },
                    'user' => function($query) {
                        $query->withTrashed();
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($send) {
                    // If receiver_id is missing but receiver_address exists (for internal transactions)
                    $receiver = $send->receiver;
                    if (!$receiver && $send->transaction_type === 'internal' && $send->receiver_address) {
                        // Try to find receiver by email or virtual account
                        $receiver = \App\Models\User::withTrashed()
                            ->where('email', $send->receiver_address)
                            ->orWhereHas('virtualAccounts', function($q) use ($send) {
                                $q->where('account_id', $send->receiver_virtual_account_id);
                            })
                            ->first();
                    }
                    
                    return [
                        'id' => $send->id,
                        'user_id' => $send->user_id,
                        'receiver_id' => $send->receiver_id,
                        'transaction_id' => $send->transaction_id,
                        'transaction_type' => $send->transaction_type,
                        'sender_virtual_account_id' => $send->sender_virtual_account_id,
                        'receiver_virtual_account_id' => $send->receiver_virtual_account_id,
                        'sender_address' => $send->sender_address,
                        'receiver_address' => $send->receiver_address,
                        'amount' => $send->amount,
                        'currency' => $send->currency,
                        'tx_id' => $send->tx_id,
                        'block_height' => $send->block_height,
                        'block_hash' => $send->block_hash,
                        'gas_fee' => $send->gas_fee,
                        'network_fee' => $send->network_fee,
                        'status' => $send->status,
                        'blockchain' => $send->blockchain,
                        'amount_usd' => $send->amount_usd,
                        'created_at' => $send->created_at,
                        'updated_at' => $send->updated_at,
                        'transaction' => $send->transaction,
                        'sender' => $send->user ? [
                            'id' => $send->user->id,
                            'name' => $send->user->name,
                            'fullName' => $send->user->fullName,
                            'email' => $send->user->email,
                            'is_deleted' => $send->user->deleted_at !== null,
                        ] : null,
                        'receiver' => $receiver ? [
                            'id' => $receiver->id,
                            'name' => $receiver->name,
                            'fullName' => $receiver->fullName,
                            'email' => $receiver->email,
                            'is_deleted' => $receiver->deleted_at !== null,
                        ] : null,
                    ];
                });

            // Get all buy transactions
            $buyTransactions = \App\Models\BuyTransaction::where('user_id', $userId)
                ->with(['bankAccount' => function($query) {
                    $query->withTrashed();
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get all swap transactions
            $swapTransactions = \App\Models\SwapTransaction::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get all withdraw transactions (through transaction relationship since withdraw_transactions doesn't have user_id)
            $withdrawTransactions = \App\Models\WithdrawTransaction::whereHas('transaction', function($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->with([
                    'transaction',
                    'withdraw_request' => function($query) {
                        $query->withTrashed()->with(['bankAccount' => function($q) {
                            $q->withTrashed();
                        }]);
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get received assets (on-chain deposits) - get all without any filters
            $receivedAssets = \App\Models\ReceivedAsset::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Debug: Log count to verify all are retrieved
            \Illuminate\Support\Facades\Log::info("Received Assets Count for User {$userId}: " . $receivedAssets->count());

            // Get referral earnings
            $referralEarnings = \App\Models\ReferalEarning::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Build comprehensive response
            $data = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'fullName' => $user->fullName,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_code' => $user->user_code,
                    'invite_code' => $user->invite_code,
                    'role' => $user->role,
                    'kyc_status' => $user->kyc_status,
                    'is_active' => $user->is_active,
                    'is_freezon' => $user->is_freezon,
                    'profile_picture' => $user->profile_picture,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'deleted_at' => $user->deleted_at,
                    'is_deleted' => $user->deleted_at !== null,
                ],
                'user_account' => $userAccount,
                'bank_accounts' => $bankAccounts,
                'kyc' => $kyc,
                'transactions_by_type' => [
                    'withdraw' => [
                        'requests' => $withdrawRequests->values(),
                        'transactions' => $withdrawTransactions->values(),
                        'count' => $withdrawRequests->count() + $withdrawTransactions->count(),
                    ],
                    'swap' => [
                        'transactions' => $swapTransactions->values(),
                        'count' => $swapTransactions->count(),
                    ],
                    'send_internal' => [
                        'transactions' => $sendTransactions->where('transaction_type', 'internal')->values(),
                        'count' => $sendTransactions->where('transaction_type', 'internal')->count(),
                    ],
                    'send_on_chain' => [
                        'transactions' => $sendTransactions->where('transaction_type', 'on_chain')->values(),
                        'count' => $sendTransactions->where('transaction_type', 'on_chain')->count(),
                    ],
                    'receive_internal' => [
                        'transactions' => $receiveTransactions->where('transaction_type', 'internal')->values(),
                        'count' => $receiveTransactions->where('transaction_type', 'internal')->count(),
                    ],
                    'receive_on_chain' => [
                        'transactions' => $receiveTransactions->where('transaction_type', 'on_chain')->values(),
                        'count' => $receiveTransactions->where('transaction_type', 'on_chain')->count(),
                    ],
                    'received_assets' => [
                        'assets' => $receivedAssets->values(),
                        'count' => $receivedAssets->count(),
                    ],
                    'buy' => [
                        'transactions' => $buyTransactions->values(),
                        'count' => $buyTransactions->count(),
                    ],
                ],
                'referral_earnings' => $referralEarnings,
                'user_activity' => $userActivity,
                'summary' => [
                    'total_withdraw_requests' => $withdrawRequests->count(),
                    'total_bank_accounts' => $bankAccounts->count(),
                    'total_received_assets' => $receivedAssets->count(),
                    'total_referral_earnings' => $referralEarnings->count(),
                    'naira_balance' => $userAccount ? $userAccount->naira_balance : 0,
                    'crypto_balance' => $userAccount ? $userAccount->crypto_balance : 0,
                ],
            ];

            return ResponseHelper::success($data, 'Complete user details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
