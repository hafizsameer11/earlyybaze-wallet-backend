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

            // Get all transactions (including soft deleted if any)
            $transactions = \App\Models\Transaction::where('user_id', $userId)
                ->with([
                    'sendtransaction',
                    'recievetransaction',
                    'buytransaction.bankAccount',
                    'swaptransaction',
                    'withdraw_transaction.withdraw_request.bankAccount'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get all withdraw requests (including soft deleted)
            $withdrawRequests = \App\Models\WithdrawRequest::withTrashed()
                ->where('user_id', $userId)
                ->with(['bankAccount' => function($query) {
                    $query->withTrashed();
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get all virtual accounts (on-chain assets)
            $virtualAccounts = \App\Models\VirtualAccount::where('user_id', $userId)
                ->with(['walletCurrency', 'depositAddresses'])
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

            // Get all receive transactions
            $receiveTransactions = \App\Models\ReceiveTransaction::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get all send transactions
            $sendTransactions = \App\Models\TransactionSend::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

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

            // Get all withdraw transactions
            $withdrawTransactions = \App\Models\WithdrawTransaction::where('user_id', $userId)
                ->with(['withdraw_request' => function($query) {
                    $query->withTrashed()->with(['bankAccount' => function($q) {
                        $q->withTrashed();
                    }]);
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get received assets
            $receivedAssets = \App\Models\ReceivedAsset::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

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
                'virtual_accounts' => $virtualAccounts,
                'bank_accounts' => $bankAccounts,
                'kyc' => $kyc,
                'transactions' => [
                    'all' => $transactions,
                    'count' => $transactions->count(),
                    'by_type' => [
                        'send' => $transactions->where('type', 'send')->values(),
                        'receive' => $transactions->where('type', 'receive')->values(),
                        'buy' => $transactions->where('type', 'buy')->values(),
                        'swap' => $transactions->where('type', 'swap')->values(),
                        'withdraw' => $transactions->where('type', 'withdrawTransaction')->values(),
                    ],
                ],
                'withdraw_requests' => [
                    'all' => $withdrawRequests,
                    'count' => $withdrawRequests->count(),
                    'by_status' => [
                        'pending' => $withdrawRequests->where('status', 'pending')->values(),
                        'approved' => $withdrawRequests->where('status', 'approved')->values(),
                        'rejected' => $withdrawRequests->where('status', 'rejected')->values(),
                    ],
                ],
                'receive_transactions' => $receiveTransactions,
                'send_transactions' => $sendTransactions,
                'buy_transactions' => $buyTransactions,
                'swap_transactions' => $swapTransactions,
                'withdraw_transactions' => $withdrawTransactions,
                'received_assets' => $receivedAssets,
                'referral_earnings' => $referralEarnings,
                'user_activity' => $userActivity,
                'summary' => [
                    'total_transactions' => $transactions->count(),
                    'total_withdraw_requests' => $withdrawRequests->count(),
                    'total_virtual_accounts' => $virtualAccounts->count(),
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
