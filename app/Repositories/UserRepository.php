<?php

namespace App\Repositories;

use App\Http\Requests\KycRequest;
use App\Models\ExchangeRate;
use App\Models\InAppNotification;
use App\Models\Kyc;
use App\Models\NairaWallet;
use App\Models\User;
use App\Models\UserAccount;
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
        $userAccount = UserAccount::where('user_id', $userId)->first();
        $virtualAccounts = $virtualAccounts->map(function ($account) use ($userAccount) {
            $u = User::where('id', $account->user_id)->first();
            $exchangeRate = ExchangeRate::where('currency', $account->currency)->first();
            $amountUsd = $account->walletCurrency->price;
            if ($exchangeRate) {
                $balance = (string) $account->available_balance;
                $rate = (string) $exchangeRate->rate_usd;

                // Clean exponential numbers (e.g., 3.0E-6) to plain string
                if (stripos($balance, 'e') !== false) {
                    $balance = sprintf('%.10f', (float)$balance);
                }
                if (stripos($rate, 'e') !== false) {
                    $rate = sprintf('%.10f', (float)$rate);
                }

                Log::info('Balance: ' . $balance);
                if (is_numeric($balance) && is_numeric($rate)) {
                    $amountUsd = bcmul($balance, $rate, 2);
                } else {
                    $amountUsd = '0.00';
                }
            }
            return [
                'id' => $account->id,
                'name' => $account->walletCurrency->name,
                'currency' => $account->currency,
                'blockchain' => $account->blockchain,
                'currency_id' => $account->currency_id,
                'available_balance' => $account->available_balance,
                'account_balance' => $account->account_balance,
                'deposit_addresses' => $account->depositAddresses,
                'status' => $account->active == true ? 'active' : 'inactive',
                'nairaWallet' => $userAccount->naira_balance ?? null,
                'freezed' => $account->frozen,
                'nairaFreeze' => $u->is_freezon,
                'wallet_currency' => [
                    'id' => $account->walletCurrency->id,
                    'price' => $amountUsd,
                    'symbol' => $account->walletCurrency->symbol,
                    'naira_price' => $account->walletCurrency->naira_price,
                    'name' => $account->walletCurrency->name

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
        $users = User::with('kyc')->orderBy('created_at', 'desc')->get();
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'img' => asset('storage/' . $user->profile_picture),
                'kyc' => $user->kyc
            ];
        });
    }
    public function userDetails($userId)
    {
        $user = User::where('id', $userId)->with('userAccount', 'userActivity')->first();
        $kycdetails = Kyc::where('user_id', $userId)->latest()->first();
        $notifications = InAppNotification::all();
        $user = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'img' => asset('storage/' . $user->profile_picture),
            'total_amount_in_dollar' => $user->userAccount->crypto_balance ?? '0',
            'total_amount_in_naira' => $user->userAccount->naira_balance ?? '0',
            'kyc_status' => $user->kyc_status,
            'user_activity' => $user->userActivity,
            'kycDetails' => $kycdetails,
            'notifcations' => $notifications,
            'role' => $user->role
        ];
        return $user;
    }

    public function getNonUsers()
    {
        $users = User::whereNot('role', 'user')->get();
        return $users;
    }
    public function getUserBalances()
    {
        // ===== VirtualAccount Stats =====
        $balances = VirtualAccount::with('walletCurrency')
            ->selectRaw('currency_id, COUNT(*) as account_count, SUM(available_balance) as total_balance')
            ->groupBy('currency_id')
            ->get();

        $totalUsd = 0;
        $totalWallets = 0;

        $mapped = $balances->map(function ($item) use (&$totalUsd, &$totalWallets) {
            $exchangeRate = \App\Models\ExchangeRate::where('currency', $item->walletCurrency->currency)->latest()->first();

            $usdBalance = 0;
            if ($exchangeRate) {
                $usdBalance = bcmul($item->total_balance, $exchangeRate->rate_usd, 8);
            }

            $totalUsd = bcadd($totalUsd, $usdBalance, 8);
            $totalWallets += $item->account_count;

            return [
                'currency' => $item->walletCurrency,
                'total_balance' => $item->total_balance,
                'usd_balance' => $usdBalance,
                'account_count' => $item->account_count
            ];
        });

        $totalNgn = bcmul($totalUsd, 1550, 2);

        // ===== Naira Balance Stats =====
        $userAccounts = \App\Models\UserAccount::with('user:id,name,email') // you can include more fields if needed
            ->select('user_id', 'naira_balance')
            ->get();

        $totalNairaBalance = $userAccounts->sum('naira_balance');
        $totalNairaWallets = $userAccounts->count();

        $userNairaBalances = $userAccounts->map(function ($item) {
            return [
                'user' => $item->user,
                'naira_balance' => $item->naira_balance
            ];
        });

        // ===== Final Response =====
        return [
            'balances' => $mapped,
            'total_wallets' => $totalWallets,
            'total_usd_balance' => $totalUsd,
            'total_ngn_balance' => $totalNgn,

            'total_naira_balance' => $totalNairaBalance,
            'total_naira_wallets' => $totalNairaWallets,
            'naira_wallets' => $userNairaBalances,
        ];
    }



    public function getBalanceByCurrency($currencyId)
    {
        // Fetch all virtual accounts for the given currency with their user and walletCurrency relationships
        $virtualAccounts = VirtualAccount::with(['user', 'walletCurrency'])
            ->where('currency_id', $currencyId)
            ->get();

        // Get exchange rate for this currency
        $walletCurrency = $virtualAccounts->first()?->walletCurrency;
        $currencyCode = $walletCurrency?->currency;

        $exchangeRate = \App\Models\ExchangeRate::latest('created_at')
            ->where('currency', $currencyCode)
            ->first();

        $rateUsd = $exchangeRate?->rate_usd ?? 0;

        // Totals
        $totalBalanceInCoins = '0';
        $totalBalanceInUsd = '0';
        $totalAccountCount = $virtualAccounts->count();

        // Map each virtual account with USD balance
        $accounts = $virtualAccounts->map(function ($account) use (&$totalBalanceInCoins, &$totalBalanceInUsd, $rateUsd) {
            $usdBalance = bcmul($account->available_balance, $rateUsd, 8);

            // Totals
            $totalBalanceInCoins = bcadd($totalBalanceInCoins, $account->available_balance, 8);
            $totalBalanceInUsd = bcadd($totalBalanceInUsd, $usdBalance, 8);

            return [
                'id' => $account->id,
                'user' => $account->user,
                'available_balance' => $account->available_balance,
                'usd_balance' => $usdBalance,
                'account_id' => $account->account_id,
                'account_code' => $account->account_code,
                'active' => $account->active,
                'frozen' => $account->frozen,
            ];
        });

        return [
            'currency' => $walletCurrency,
            'accounts' => $accounts,
            'total_balance_in_coins' => $totalBalanceInCoins,
            'total_balance_in_usd' => $totalBalanceInUsd,
            'total_account_count' => $totalAccountCount,
        ];
    }



    // public function
}
