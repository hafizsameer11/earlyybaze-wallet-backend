<?php

namespace App\Repositories;

use App\Models\NairaWallet;
use App\Models\User;
use App\Models\VirtualAccount;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    protected $BankAccountRepository;
    public function __construct(BankAccountRepository $BankAccountRepository)
    {
        $this->BankAccountRepository = $BankAccountRepository;
    }
    public function create(array $data): User
    {
        //check if profit_pic
        // Log::info($data);
        if (isset($data['profile_picture']) && $data['profile_picture']) {
            $path = $data['profile_picture']->store('profile_picture', 'public');
            $data['profile_picture'] = $path;
        }
        return User::create($data);
    }
    public function createNairaWallet(User $user)
    {
        return NairaWallet::create([
            'user_id' => $user->id,
            'balance' => 0
        ]);
    }
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }
    public function delete(User $user): void
    {
        $user->delete();
    }
    public function getById(int $id)
    {
        return User::find($id);
    }
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return User::all();
    }

    public function findByUserCode($userCode): ?User
    {
        return User::where('user_code', $userCode)->first();
    }
    public function findByEmail($email): ?User
    {
        return User::where('email', $email)->first();
    }
    public function setPin(User $user, string $pin): User
    {
        $user->pin = $pin;
        $user->save();
        return $user;
    }
    public function verifyPin(User $user, string $pin): bool
    {
        return $user->pin === $pin;
    }
    public function changePassword(string $oldPassword, string $newPassword, $userId): ?User
    {
        $user = User::find($userId);

        if (!Hash::check($oldPassword, $user->password)) {
            throw new Exception('Invalid old password');
        }
        $user->password = Hash::make($newPassword);
        $user->save();
        return $user;
    }
    public function getuserAssets($userId)
    {
        $virtualAccounts = VirtualAccount::where('user_id', $userId)->with('walletCurrency', 'depositAddresses')->get();

        $virtualAccounts = $virtualAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'currency' => $account->currency,
                'blockchain' => $account->blockchain,
                'currency_id' => $account->currency_id,
                'available_balance' => $account->available_balance,
                'account_balance' => $account->account_balance,
                'deposit_addresses' => $account->depositAddresses,
                'wallet_currency' => [
                    'id' => $account->walletCurrency->id,
                    'price' => $account->walletCurrency->price,
                    'symbol' => $account->walletCurrency->symbol,
                    'naira_price' => $account->walletCurrency->naira_price
                ]
            ];
        });
        return $virtualAccounts;
    }
    public function walletCurrenyforUser($userId)
    {
        //get the wallet currency for the user from the virtual account having balance
        $walletCurrency = VirtualAccount::where('user_id', $userId)->where('available_balance', '>', 0)->with('walletCurrency')->get();
        $walletCurrency = $walletCurrency->map(function ($account) {
            return [
                'balance' => $account->available_balance,
                'currency' => $account->walletCurrency
            ];
        });
        return $walletCurrency;
    }
    public function getDepostiAddress($userId, $currency, $network)
    {
        $virtualAccount = VirtualAccount::where('user_id', $userId)->where('currency', $currency)->where('blockchain', $network)->with('depositAddresses')->orderBy('created_at', 'desc')->first();
        if (!$virtualAccount) {
            throw new Exception('No virtual account found');
        }
        return $virtualAccount->depositAddresses()->orderBy('created_at', 'desc')->first();
    }

    public function allwalletcurrenciesforuser($userId)
    {
        $walletCurrency = VirtualAccount::where('user_id', $userId)->with('walletCurrency')->get();
        $walletCurrency = $walletCurrency->map(function ($account) {
            return [
                'balance' => $account->available_balance,
                'currency' => $account->walletCurrency
            ];
        });
        return $walletCurrency;
    }
    public function updateUserProfile(string $userId, array $data): User
    {

        $user = User::find($userId);
        if (isset($data['profile_picture']) && $data['profile_picture']) {
            $path = $data['profile_picture']->store('profile_picture', 'public');
            $data['profile_picture'] = $path;
        }
        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        return $user;
    }

    public function getUserManagementData()
    {
        $totalUser = User::count();
        $totalActiveUser = User::where('is_active', true)->count();
        $totalInactiveUser = User::where('is_active', false)->count();
        $stats = [
            [
                'heading' => 'Total',
                'subheading' => 'Users',
                'icon' => 'userIcon',
                'cardValue' => $totalUser,
                'iconBg' => 'bg-[#126EB9]',
                'valueStatus' => false
            ],
            [
                'heading' => 'Online',
                'subheading' => 'Users',
                'icon' => 'userIcon',
                'cardValue' => $totalActiveUser,
                'iconBg' => 'bg-[#126EB9]',
                'valueStatus' => false
            ],
            [
                'heading' => 'Offline',
                'subheading' => 'Users',
                'icon' => 'userIcon',
                'cardValue' => $totalInactiveUser,
                'iconBg' => 'bg-[#126EB9]',
                'valueStatus' => false
            ],
        ];
        $users = $this->getFomatedUsers();
        return [
            'stats' => $stats,
            'users' => $users
        ];
    }
    public function getFomatedUsers()
    {
        $users = User::all();
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'img' => asset('storage/' . $user->profile_picture)
            ];
        });
    }
    public function userDetails($userId)
    {
        $user = User::where('id', $userId)->with('userAccount')->first();
        // $user = $user->map(function ($user) {
        $user = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'img' => asset('storage/' . $user->profile_picture),
            'total_amount_in_dollar' => $user->userAccount->crypto_balance,
            'total_amount_in_naira' => $user->userAccount->naira_balance
        ];
        // });
        return $user;
    }
}
