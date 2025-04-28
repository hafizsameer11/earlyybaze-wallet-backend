<?php

namespace App\Services;

use App\Jobs\CreateVirtualAccount;
use App\Mail\OtpMail;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Repositories\UserAccountRepository;
use App\Repositories\UserRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{
    protected $userRepository;
    protected $userAccountRepository;
    protected  $tatumService;
    public function __construct(UserRepository $userRepository, UserAccountRepository $userAccountRepository, TatumService $tatumService)
    {
        $this->userRepository = $userRepository;
        $this->userAccountRepository = $userAccountRepository;
        $this->tatumService = $tatumService;
    }
    public function getUserDetails()
    {
        try {
            $user = Auth::user();
            return $this->userRepository->getById($user->id);
        } catch (Exception $e) {
            Log::error('Get user details error: ' . $e->getMessage());
            throw new Exception('Get user details failed.');
        }
    }
    public function getUserAccounts()
    {
        try {
            $user = Auth::user();
            $customer_id = VirtualAccount::where('user_id', $user->id)->first()->customer_id;
            // return $user->id;
            return $this->tatumService->getUserAccounts($customer_id);
        } catch (Exception $e) {
            Log::error('Get user accounts error: ' . $e->getMessage());
            throw new Exception('Get user accounts failed.');
        }
    }
    public function registerUser(array $data): ?User
    {
        try {
            $data['password'] = Hash::make($data['password']);
            $data['otp'] = rand(100000, 999999);
            $data['user_code'] = $this->generateUserCode();
            $user = $this->userRepository->create($data);
            Mail::to($user->email)->send(new OtpMail($user->otp));
            $this->userRepository->createNairaWallet($user);
            $accountNumber = $this->generateAccountNumber();
            $this->userAccountRepository->create([
                'user_id' => $user->id,
                'account_number' => $accountNumber
            ]);
            return $user;
        } catch (Exception $e) {
            Log::error('User registration error: ' . $e->getMessage());
            throw new Exception('User registration failed.');
        }
    }
    public function createUser(array $data): ?User
    {
        try {
            $data['password'] = Hash::make($data['password']);
            $data['otp'] = rand(100000, 999999);
            $data['user_code'] = $this->generateUserCode();
            $user = $this->userRepository->create($data);
            // Mail::to($user->email)->send(new OtpMail($user->otp));
            // $this->userRepository->createNairaWallet($user);
            // $accountNumber = $this->generateAccountNumber();
            // $this->userAccountRepository->create([
            //     'user_id' => $user->id,
            //     'account_number' => $accountNumber
            // ]);
            dispatch(new CreateVirtualAccount($user));
            $user->otp_verified = true;
            $user->save();
            return $user;
        } catch (Exception $e) {
            Log::error('User registration error: ' . $e->getMessage());
            throw new Exception('User registration failed.');
        }
    }
    private function generateUserCode(): string
    {
        do {
            $randomNumber = rand(100000, 999999);
            $userCode = 'EarlyBaze' . $randomNumber;
        } while ($this->userRepository->findByUserCode($userCode));

        return $userCode;
    }
    public function verifyOtp(array $data): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($data['email']);
            if (!$user) {
                throw new Exception('User not found.');
            }
            if ($user->otp !== $data['otp']) {
                throw new Exception('Invalid OTP.');
            }
            $user->otp = null;
            $user->otp_verified = true;
            $user->save();
            dispatch(new CreateVirtualAccount($user));
            return $user;
        } catch (Exception $e) {
            Log::error('OTP verification error: ' . $e->getMessage());
            throw new Exception('OTP verification failed.');
        }
    }

    public function login(array $data): ?array
    {
        try {
            $user = $this->userRepository->findByEmail($data['email']);

            if (!$user) {
                throw new Exception('User not found.');
            }
            if (!$user->otp_verified) {
                throw new Exception('OTP verification required.');
            }
            if (!Hash::check($data['password'], $user->password)) {
                throw new Exception('Invalid password.');
            }
            $token = $user->createToken('auth_token')->plainTextToken;
            $userData = $user->only(['id', 'email']);
            $virtualAccounts = $user->virtualAccounts()->select(['id', 'currency', 'blockchain', 'currency_id', 'available_balance', 'account_balance'])->get();
            $virtualAccounts->each(function ($account) {
                $account->walletCurrency = $account->walletCurrency()
                    ->select(['id', 'price', 'symbol', 'naira_price'])
                    ->first();
            });
            return [
                'user' => $userData,
                'virtual_accounts' => $virtualAccounts,
                'token' => $token
            ];
        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            throw new Exception('Login failed ' . $e->getMessage());
        }
    }
    public function adminLogin(array $data): ?array
    {
        try {
            $user = $this->userRepository->findByEmail($data['email']);
            if (!$user) {
                throw new Exception('User not found.');
            }
            if ($user->role =='user') {
                throw new Exception('User is not an admin.');
            }
            if (!Hash::check($data['password'], $user->password)) {
                throw new Exception('Invalid password.');
            }
            $token = $user->createToken('auth_token')->plainTextToken;
            // $userData = $user->only(['id', 'email']);
            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (Exception $e) {
            Log::error('Admin login error: ' . $e->getMessage());
            throw new Exception('Admin login failed ' . $e->getMessage());
        }
    }
    public function setPin(string $email, string $pin): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            return $this->userRepository->setPin($user, $pin);
        } catch (Exception $e) {
            Log::error('Set pin error: ' . $e->getMessage());
            throw new Exception('Set pin failed.');
        }
    }
    public function verifyPin(string $pin, string $email): ?User
    {
        try {

            $user = $this->userRepository->findByEmail($email);
            $status = $this->userRepository->verifyPin($user, $pin);
            if (!$status) {
                throw new Exception('Invalid pin.');
            }
            return $user;
        } catch (Exception $e) {
            Log::error('Verify pin error: ' . $e->getMessage());
            throw new Exception('Verify pin failed.');
        }
    }
    public function resendOtp(string $email): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            $user->otp = rand(100000, 999999);
            $user->save();
            Mail::to($user->email)->send(new OtpMail($user->otp));
            return $user;
        } catch (Exception $e) {
            Log::error('Resend OTP error: ' . $e->getMessage());
            throw new Exception('Resend OTP failed.');
        }
    }
    public function changePassword(string $oldPassword, string $newPassword): ?User
    {
        try {
            $Authuser = Auth::user();
            $user = $this->userRepository->changePassword($oldPassword, $newPassword, $Authuser->id);
            return $user;
        } catch (Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());
            throw new Exception('Change password failed. ' . $e->getMessage());
        }
    }
    private function generateAccountNumber(): string
    {
        $randomNumber = 'EarlyBaze-' . rand(1000000000, 9999999999);
        return $randomNumber;
    }
    public function getUserAssets()
    {
        try {
            $user = Auth::user();
            $virtualAccounts = $this->userRepository->getuserAssets($user->id);

            return $virtualAccounts;
        } catch (Exception $e) {
            Log::error('Get user assets error: ' . $e->getMessage());
            throw new Exception('Get user assets failed. ' . $e->getMessage());
        }
    }
    public function getwalletcurrenciesforuser()
    {
        try {
            $user = Auth::user();
            $walletCurrencies = $this->userRepository->walletCurrenyforUser($user->id);
            return $walletCurrencies;
        } catch (Exception $e) {
            Log::error('Get wallet currencies error: ' . $e->getMessage());
            throw new Exception('Get wallet currencies failed. ' . $e->getMessage());
        }
    }
    public function getDepostiAddress($currency, $network)
    {
        try {
            $user = Auth::user();
            $depositAddress = $this->userRepository->getDepostiAddress($user->id, $currency, $network);
            return $depositAddress;
        } catch (Exception $e) {
            Log::error('Get deposit address error: ' . $e->getMessage());
            throw new Exception('Get deposit address failed. ' . $e->getMessage());
        }
    }
    public function allwalletcurrenciesforuser()
    {
        try {
            $user = Auth::user();
            $walletCurrencies = $this->userRepository->allwalletcurrenciesforuser($user->id);
            return $walletCurrencies;
        } catch (Exception $e) {
            Log::error('Get wallet currencies error: ' . $e->getMessage());
            throw new Exception('Get wallet currencies failed. ' . $e->getMessage());
        }
    }
    public function updateUserProfile(array $data, $userId): ?User
    {
        try {
            // $user = Auth::user();
            return $this->userRepository->updateUserProfile($userId, $data);
        } catch (Exception $e) {
            Log::error('Update user profile error: ' . $e->getMessage());
            throw new Exception('Update user profile failed. ' . $e->getMessage());
        }
    }

    public function getUserManagementData()
    {
        try {
            return $this->userRepository->getUserManagementData();
        } catch (Exception $e) {
            Log::error('Get user management data error: ' . $e->getMessage());
            throw new Exception('Get user management data failed. ' . $e->getMessage());
        }
    }

    public function userDetails($userId)
    {
        try {
            return $this->userRepository->userDetails($userId);
        } catch (Exception $e) {
            Log::error('Get user details error: ' . $e->getMessage());
            throw new Exception('Get user details failed. ' . $e->getMessage());
        }
    }
    public function getUserVirtualAccounts($userId)
    {
        try {
            return $this->userRepository->getuserAssets($userId);
        } catch (Exception $e) {
            Log::error('Get user virtual accounts error: ' . $e->getMessage());
            throw new Exception('Get user virtual accounts failed. ' . $e->getMessage());
        }
    }
    public function getNonUsers()
    {
        try {
            return $this->userRepository->getNonUsers();
        } catch (Exception $e) {
            Log::error('Get non users error: ' . $e->getMessage());
            throw new Exception('Get non users failed. ' . $e->getMessage());
        }
    }
    public function getUserBalances()
    {
        try {
            return $this->userRepository->getUserBalances();
        } catch (Exception $e) {
            Log::error('Get user balances error: ' . $e->getMessage());
            throw new Exception('Get user balances failed. ' . $e->getMessage());
        }
    }
    public function getBalanceByCurrency($currencyId)
    {
        try {
            return $this->userRepository->getBalanceByCurrency($currencyId);
        } catch (Exception $e) {
            Log::error('Get balance by currency error: ' . $e->getMessage());
            throw new Exception('Get balance by currency failed. ' . $e->getMessage());
        }
    }
}
