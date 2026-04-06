<?php

namespace App\Services;

use App\Models\DepositAddress;
use App\Models\User;
use App\Models\UserBlockchainWallet;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Support\WalletFlowV2;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserWalletV2Provisioner
{
    public function __construct(
        private TatumV4SubscriptionService $subscriptions
    ) {}

    public function provision(User $user): void
    {
        $apiKey = config('tatum.api_key');
        $base = rtrim(config('tatum.base_url'), '/');

        $byChain = [];
        foreach (WalletCurrency::all() as $wc) {
            if (! WalletFlowV2::currencyAllowedForV2($wc)) {
                continue;
            }
            $ck = WalletFlowV2::resolveChainKey((string) $wc->blockchain);
            if (! $ck) {
                continue;
            }
            $byChain[$ck][] = $wc;
        }

        foreach ($byChain as $chainKey => $currencies) {
            $this->provisionChainGroup($user, $chainKey, $currencies, $apiKey, $base);
        }
    }

    /**
     * @param  array<int, WalletCurrency>  $currencies
     */
    private function provisionChainGroup(User $user, string $chainKey, array $currencies, string $apiKey, string $base): void
    {
        $profile = WalletFlowV2::chainProfile($chainKey);
        if (! $profile) {
            return;
        }

        $ubw = UserBlockchainWallet::firstOrNew([
            'user_id' => $user->id,
            'chain_key' => $chainKey,
        ]);

        if (! $ubw->primary_address) {
            $walletResp = Http::withHeaders(['x-api-key' => $apiKey])
                ->get($base.$profile['wallet_endpoint']);

            if ($walletResp->failed()) {
                Log::error('Wallet v2: Tatum wallet create failed', [
                    'chain' => $chainKey,
                    'body' => $walletResp->body(),
                ]);

                return;
            }

            $wallet = $walletResp->json();
            $mnemonic = $wallet['mnemonic'] ?? null;
            $xpub = $wallet['xpub'] ?? null;
            $address = $wallet['address'] ?? null;
            $privateKey = $wallet['privateKey'] ?? null;

            $prefix = $profile['address_prefix'];

            if (! $address && $xpub) {
                $addrResp = Http::withHeaders(['x-api-key' => $apiKey])
                    ->get($base.'/'.$prefix.'/address/'.rawurlencode($xpub).'/0');
                if ($addrResp->successful()) {
                    $address = $addrResp->json('address');
                }
            }

            if (! $privateKey && $mnemonic) {
                $privResp = Http::withHeaders(['x-api-key' => $apiKey])
                    ->post($base.'/'.$prefix.'/wallet/priv', [
                        'index' => 0,
                        'mnemonic' => $mnemonic,
                    ]);
                if ($privResp->successful()) {
                    $privateKey = $privResp->json('key') ?? $privResp->json('privateKey');
                }
            }

            if (! $address || ! $privateKey) {
                Log::error('Wallet v2: missing address or private key', ['chain' => $chainKey]);

                return;
            }

            $ubw->mnemonic_ciphertext = $mnemonic ? Crypt::encryptString($mnemonic) : null;
            $ubw->private_key_ciphertext = Crypt::encryptString($privateKey);
            $ubw->xpub = $xpub;
            $ubw->primary_address = $address;
            $ubw->tatum_wallet_response = $wallet;
            $ubw->save();
        }

        $v4Chain = WalletFlowV2::v4ChainForProfile($profile);
        $address = $ubw->primary_address;
        $privateKeyEncrypted = $ubw->private_key_ciphertext;

        $nativeSubId = null;
        $nativeSubCreated = false;

        foreach ($currencies as $walletCurrency) {
            if (VirtualAccount::where('user_id', $user->id)->where('currency_id', $walletCurrency->id)->exists()) {
                continue;
            }

            $isToken = (bool) ($walletCurrency->is_token ?? false);

            $virtualAccount = VirtualAccount::create([
                'user_id' => $user->id,
                'blockchain' => $walletCurrency->blockchain,
                'currency' => $walletCurrency->currency,
                'customer_id' => null,
                'account_id' => WalletFlowV2::syntheticAccountId($user->id, $walletCurrency->id),
                'account_code' => $user->user_code,
                'active' => true,
                'frozen' => false,
                'account_balance' => '0',
                'available_balance' => '0',
                'xpub' => $ubw->xpub ?? 'v2-managed',
                'accounting_currency' => 'USD',
                'currency_id' => $walletCurrency->id,
                'is_tatum_ledger' => false,
            ]);

            $deposit = DepositAddress::create([
                'virtual_account_id' => $virtualAccount->id,
                'version' => 'v2',
                'blockchain' => strtolower((string) $walletCurrency->blockchain),
                'currency' => $walletCurrency->currency,
                'address' => $address,
                'index' => 0,
                'private_key' => $privateKeyEncrypted,
                'tatum_v4_chain' => $v4Chain,
            ]);

            if ($isToken) {
                $contract = $this->resolveContractForSubscription($walletCurrency);
                if ($contract) {
                    $fid = $this->subscriptions->subscribeFungible($v4Chain, $address, $contract);
                    if ($fid) {
                        $deposit->tatum_subscription_fungible_id = $fid;
                        $deposit->save();
                    }
                }
            } else {
                if (! $nativeSubCreated) {
                    $nativeSubId = $this->subscriptions->subscribeNative($v4Chain, $address);
                    if ($nativeSubId) {
                        $nativeSubCreated = true;
                        $deposit->tatum_subscription_native_id = $nativeSubId;
                        $deposit->save();
                    }
                } else {
                    $deposit->tatum_subscription_native_id = $nativeSubId;
                    $deposit->save();
                }
            }
        }

        if (! $nativeSubCreated) {
            $nativeSubId = $this->subscriptions->subscribeNative($v4Chain, $address);
            if ($nativeSubId) {
                DepositAddress::query()
                    ->where('version', 'v2')
                    ->where('address', $address)
                    ->whereHas('virtualAccount', fn ($q) => $q->where('user_id', $user->id))
                    ->whereNull('tatum_subscription_native_id')
                    ->update(['tatum_subscription_native_id' => $nativeSubId]);
            }
        }
    }

    private function resolveContractForSubscription(WalletCurrency $wc): ?string
    {
        $c = trim((string) ($wc->contract_address ?? ''));
        if ($c !== '') {
            return $c;
        }

        $cur = strtoupper((string) $wc->currency);
        if ($cur === 'USDT_TRON') {
            return 'USDT_TRON';
        }
        if ($cur === 'USDC_TRON') {
            return 'USDC_TRON';
        }

        return null;
    }
}
