<?php

namespace App\Repositories;

use App\Helpers\ExchangeFeeHelper;
use App\Models\DepositAddress;
use App\Models\ExchangeRate;
use App\Models\MasterWallet;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\Auth;

class ExchangeRateRepository
{
    public function all()
    {
        return ExchangeRate::all();
    }

    public function find($id)
    {
        $exchangeRate = ExchangeRate::find($id);
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        return $exchangeRate;
        //    if()
    }
    public function getByCurrency($currency)
    {

        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        return $exchangeRate;
    }
    public function create(array $data)
    {
        $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->orderBy('id', 'desc')->first();
        //calculate the exchange rate in naira
        $data['rate_usd'] = 1 / $data['rate'];
        $usd_rate = $data['rate_usd'];
        $data['rate_naira'] = $nairaExchangeRate->rate * $usd_rate;
        return ExchangeRate::create($data);
    }

    public function update($id, array $data)
    {
        $exchangeRate = $this->find($id);
        $exchangeRate->update($data);
        return $exchangeRate;
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function changeStatus($id, $status)
    {
        $exchangeRate = $this->find($id);
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        $exchangeRate->status = $status;
        $exchangeRate->save();
        return $exchangeRate;
    }
    public function calculateExchangeRate($currency, $amount, $type = null, $to = null)
    {
        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
        $amountNaira = bcmul($amount, $exchangeRate->rate_naira, 8);

        $feeSummary = null;
        if ($type === 'send' && $to) {
            $isEmail = filter_var($to, FILTER_VALIDATE_EMAIL);
            if ($isEmail) {
                $from = Auth::user()->email;
            } else {
                $virtualAccount = VirtualAccount::where('user_id', Auth::user()->id)->where('currency', $currency)->first();
                if ($virtualAccount) {
                    $from = MasterWallet::where('currency', $currency)->first();
                    $from = $from->address;
                } else {
                    throw new \Exception('Virtual account not found');
                }
                // $from=
            }

            $fee = ExchangeFeeHelper::caclulateFee(
                $amount,
                $currency,
                $type,
                $isEmail ? null : 'external_transfer',
                $from,
                $to,
                auth()->id() // You may pass user ID dynamically if required
            );

            $feeSummary = [
                'platform_fee_usd' => $fee['platform_fee_usd'],
                'blockchain_fee_usd' => $fee['blockchain_fee_usd'],
                'total_fee_usd' => $fee['total_fee_usd'],
                'amount_after_fee' => bcsub($amountUsd, $fee['total_fee_usd'], 8),
            ];
        }

        return [
            'amount' => $amount,
            'amount_usd' => $amountUsd,
            'amount_naira' => $amountNaira,
            'fee_summary' => $feeSummary // null if not applicable
        ];
    }
}
