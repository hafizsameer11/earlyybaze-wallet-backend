<?php

namespace App\Services\V3;

use App\Models\Fee;
use App\Models\WithdrawRequest;
use App\Repositories\V3\V3WithdrawRequestRepository;
use App\Services\FiatBalanceService;
use Exception;
use Illuminate\Support\Facades\Auth;

class V3WithdrawRequestService
{
    public function __construct(
        protected V3WithdrawRequestRepository $repository,
        protected FiatBalanceService $fiatBalanceService,
    ) {}

    public function create(array $data): WithdrawRequest
    {
        try {
            $currency = FiatBalanceService::normalizeCurrency($data);
            if ($currency !== 'ZAR') {
                throw new Exception('V3 withdraw supports ZAR only.');
            }

            $user = Auth::user();
            $data['user_id'] = $user->id;
            $data['status'] = 'pending';
            $data['reference'] = 'EarlyBaze'.time();
            $data['currency'] = 'ZAR';
            $data['asset'] = 'zar';

            $amount = (string) $data['amount'];
            $feeType = FiatBalanceService::withdrawFeeType('ZAR');
            $fee = Fee::where('type', $feeType)->orderByDesc('id')->first()
                ?? Fee::where('type', 'withdraw')->orderByDesc('id')->first();

            if (! $fee) {
                throw new Exception('No withdraw fee defined for ZAR.');
            }

            $percentageFee = bcmul($amount, bcdiv((string) $fee->percentage, '100', 8), 8);
            $fixedFee = (string) ($fee->amount ?? 0);
            $calculatedFee = bcadd($percentageFee, $fixedFee, 8);

            $data['fee'] = $calculatedFee;
            $data['total'] = bcadd($amount, $calculatedFee, 8);

            $available = $this->fiatBalanceService->getAvailableBalance((int) $user->id, 'ZAR');
            if (bccomp($available, $data['total'], 8) < 0) {
                throw new Exception('Insufficient Balance');
            }

            return $this->repository->create($data);
        } catch (Exception $e) {
            throw new Exception('Withdraw Request Creation Failed '.$e->getMessage());
        }
    }

    public function updateStatus(int $id, array $data): WithdrawRequest
    {
        try {
            return $this->repository->updateStatus($id, $data);
        } catch (Exception $e) {
            throw new Exception('Update Withdraw Request Status Failed '.$e->getMessage());
        }
    }
}
