<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\AdminTransfer;
use App\Models\DepositAddress;
use App\Models\FailedMasterTransfer;
use App\Models\OnChainVerificationFailure;
use App\Models\ReceivedAsset;
use App\Models\RejectedDepositWebhook;
use App\Models\VirtualAccount;
use App\Services\DepositCreditingService;
use App\Services\FlushBatchExpectations;
use App\Services\FlushCompletionService;
use App\Services\TatumOnChainTxVerifier;
use App\Support\AllowedFungibleContracts;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function getTatumFailures(Request $request)
    {
        $rejectedQ = RejectedDepositWebhook::query()->with('user:id,email,name');
        $failedTransferQ = FailedMasterTransfer::query()->with([
            'virtualAccount.user:id,name,email',
            'webhookResponse:id,reference,tx_id',
        ]);

        if ($request->filled('reason')) {
            $reason = (string) $request->input('reason');
            $rejectedQ->where('rejection_reason', $reason);
            $failedTransferQ->where('reason', 'like', '%'.$reason.'%');
        }
        if ($request->filled('tx_id')) {
            $txId = (string) $request->input('tx_id');
            $rejectedQ->where('tx_id', $txId);
            $failedTransferQ->whereHas('webhookResponse', function ($q) use ($txId) {
                $q->where('tx_id', $txId);
            });
        }

        $rejectedRows = $rejectedQ->latest('id')->limit(300)->get()->map(function (RejectedDepositWebhook $row) {
            return [
                'id' => $row->id,
                'source' => 'rejected_deposit',
                'reason' => $row->rejection_reason,
                'tx_id' => $row->tx_id,
                'reference' => $row->reference,
                'chain' => $row->chain,
                'currency' => $row->payload_currency ?: $row->account_currency,
                'amount' => $row->amount,
                'user' => $row->user ? [
                    'id' => $row->user->id,
                    'name' => $row->user->name,
                    'email' => $row->user->email,
                ] : null,
                'payload_ref' => $row->reference,
                'created_at' => $row->created_at,
            ];
        });

        $failedRows = $failedTransferQ->latest('id')->limit(300)->get()->map(function (FailedMasterTransfer $row) {
            return [
                'id' => $row->id,
                'source' => 'failed_master_transfer',
                'reason' => $row->reason,
                'tx_id' => $row->webhookResponse->tx_id ?? null,
                'reference' => $row->webhookResponse->reference ?? null,
                'chain' => null,
                'currency' => null,
                'amount' => null,
                'user' => $row->virtualAccount?->user ? [
                    'id' => $row->virtualAccount->user->id,
                    'name' => $row->virtualAccount->user->name,
                    'email' => $row->virtualAccount->user->email,
                ] : null,
                'payload_ref' => $row->webhook_response_id,
                'created_at' => $row->created_at,
            ];
        });

        $merged = $rejectedRows
            ->concat($failedRows)
            ->concat($this->verificationFailureRows())
            ->sortByDesc(fn ($item) => strtotime((string) $item['created_at']))
            ->values();

        return ResponseHelper::success([
            'rows' => $merged,
            'meta' => [
                'rejected_count' => $rejectedRows->count(),
                'failed_transfer_count' => $failedRows->count(),
                'verification_failure_count' => OnChainVerificationFailure::query()->whereNull('resolved_at')->count(),
                'total' => $merged->count(),
            ],
        ], 'Tatum failure records fetched', 200);
    }

    public function getVerificationFailures(Request $request)
    {
        $q = OnChainVerificationFailure::query()->with(['user:id,email,name', 'receivedAsset']);

        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }
        if ($request->filled('failure_code')) {
            $q->where('failure_code', $request->string('failure_code'));
        }
        if ($request->filled('tx_id')) {
            $q->where('tx_id', $request->string('tx_id'));
        }
        if ($request->filled('currency')) {
            $q->where('currency', strtoupper($request->string('currency')));
        }
        if ($request->boolean('unresolved_only', true)) {
            $q->whereNull('resolved_at');
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        return ResponseHelper::success(
            $q->latest('id')->paginate($perPage),
            'Verification failures fetched',
            200
        );
    }

    public function getVerificationFailure(int $id)
    {
        $row = OnChainVerificationFailure::with(['user:id,email,name', 'receivedAsset'])->find($id);
        if (! $row) {
            return ResponseHelper::error('Verification failure not found', 404);
        }

        return ResponseHelper::success($row, 'Verification failure detail', 200);
    }

    public function approveVerificationFailure(int $id, DepositCreditingService $creditingService)
    {
        $failure = OnChainVerificationFailure::with('receivedAsset')->find($id);
        if (! $failure) {
            return ResponseHelper::error('Verification failure not found', 404);
        }
        if ($failure->type !== OnChainVerificationFailure::TYPE_DEPOSIT) {
            return ResponseHelper::error('Only deposit failures can be approved for crediting', 422);
        }

        $asset = $failure->receivedAsset;
        if (! $asset) {
            return ResponseHelper::error('Received asset not found', 404);
        }

        $account = VirtualAccount::where('user_id', $asset->user_id)
            ->where('currency', $asset->currency)
            ->first();
        if (! $account) {
            return ResponseHelper::error('Virtual account not found', 404);
        }

        $deposit = DepositAddress::query()
            ->where('virtual_account_id', $account->id)
            ->whereRaw('LOWER(address) = ?', [strtolower((string) $asset->to_address)])
            ->first();
        if (! $deposit) {
            $deposit = new DepositAddress([
                'address' => $asset->to_address,
                'virtual_account_id' => $account->id,
            ]);
        }

        if ($asset->status === 'processing' || $asset->status === 'failed') {
            $asset->status = 'processing';
            $asset->save();
            $creditingService->creditVerifiedDeposit(
                $asset,
                $failure->webhook_payload ?? [],
                $account,
                $deposit,
                (string) $asset->reference,
            );
        }

        $failure->resolved_at = now();
        $failure->resolved_by = auth()->id();
        $failure->resolution = OnChainVerificationFailure::RESOLUTION_APPROVED;
        $failure->save();

        return ResponseHelper::success($failure->fresh(['receivedAsset']), 'Deposit approved and credited', 200);
    }

    public function dismissVerificationFailure(int $id)
    {
        $failure = OnChainVerificationFailure::find($id);
        if (! $failure) {
            return ResponseHelper::error('Verification failure not found', 404);
        }

        $failure->resolved_at = now();
        $failure->resolved_by = auth()->id();
        $failure->resolution = OnChainVerificationFailure::RESOLUTION_DISMISSED;
        $failure->save();

        return ResponseHelper::success($failure, 'Verification failure dismissed', 200);
    }

    public function reverifyVerificationFailure(
        int $id,
        TatumOnChainTxVerifier $verifier,
        FlushCompletionService $flushCompletion,
        FlushBatchExpectations $batchExpectations,
    ) {
        $failure = OnChainVerificationFailure::with('receivedAsset')->find($id);
        if (! $failure) {
            return ResponseHelper::error('Verification failure not found', 404);
        }

        $asset = $failure->receivedAsset;
        if (! $asset || ! $failure->tx_id) {
            return ResponseHelper::error('Insufficient data to re-verify', 422);
        }

        if ($failure->type === OnChainVerificationFailure::TYPE_DEPOSIT) {
            $account = VirtualAccount::where('user_id', $asset->user_id)->where('currency', $asset->currency)->first();
            $deposit = new DepositAddress(['address' => $asset->to_address, 'virtual_account_id' => $account?->id]);
            $result = $verifier->verifyDeposit($failure->webhook_payload ?? [], $account, $deposit);
        } else {
            $batch = $batchExpectations->resolveFromTx((string) $failure->tx_id, (string) $asset->currency);
            $expectedFrom = $batch['expected_from'] ?? ($failure->expected_from ?: null);
            $expectedTo = $batch['expected_to'] !== '' ? $batch['expected_to'] : (string) $failure->expected_to;
            $expectedAmount = $batch['expected_amount'] ?? (string) $failure->expected_amount;

            $result = $verifier->verifyFlush(
                (string) $asset->currency,
                (string) $failure->tx_id,
                $expectedFrom,
                $expectedTo,
                $expectedAmount,
            );

            if ($result->isSuccess()) {
                $relatedIds = ($batch['pending_asset_ids'] ?? []) !== []
                    ? $batch['pending_asset_ids']
                    : ReceivedAsset::query()
                        ->where('transfered_tx', $failure->tx_id)
                        ->where('currency', $asset->currency)
                        ->where('status', '!=', 'completed')
                        ->pluck('id')
                        ->all();

                $flushCompletion->completeVerifiedFlush(
                    $relatedIds !== [] ? $relatedIds : [$asset->id],
                    (string) $asset->currency,
                    (string) $failure->tx_id,
                    $expectedFrom,
                    $expectedTo,
                    $expectedAmount,
                    $asset->gas_fee ? (float) $asset->gas_fee : null,
                    $result,
                );
                $failure = $failure->fresh(['receivedAsset']);
            }
        }

        $failure->tatum_response = $result->raw ?: $result->toArray();
        if (! $result->isSuccess()) {
            $failure->failure_code = $result->failureCode ?? $failure->failure_code;
            $failure->failure_message = $result->failureMessage;
        }
        $failure->save();

        return ResponseHelper::success([
            'failure' => $failure->fresh(['receivedAsset']),
            'verification' => $result->toArray(),
        ], $result->isSuccess() && $failure->type === OnChainVerificationFailure::TYPE_FLUSH
            ? 'Flush confirmed on chain and marked completed'
            : 'Re-verification completed', 200);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function verificationFailureRows()
    {
        $rows = OnChainVerificationFailure::query()
            ->with('user:id,email,name')
            ->whereNull('resolved_at')
            ->latest('id')
            ->limit(300)
            ->get();

        $batchAmounts = [];
        foreach ($rows as $row) {
            if ($row->type !== OnChainVerificationFailure::TYPE_FLUSH || ! $row->tx_id) {
                continue;
            }
            $key = $row->tx_id.'|'.$row->currency;
            if (isset($batchAmounts[$key])) {
                continue;
            }
            $batch = app(FlushBatchExpectations::class)->resolveFromTx((string) $row->tx_id, (string) $row->currency);
            if ($batch !== null) {
                $batchAmounts[$key] = [
                    'amount' => $batch['expected_amount'],
                    'count' => $batch['asset_count'],
                ];
            }
        }

        return $rows->map(function (OnChainVerificationFailure $row) use ($batchAmounts) {
            $key = $row->tx_id ? $row->tx_id.'|'.$row->currency : null;
            $batch = $key !== null ? ($batchAmounts[$key] ?? null) : null;

            return [
                'id' => $row->id,
                'source' => 'on_chain_verification',
                'reason' => $row->failure_code,
                'tx_id' => $row->tx_id,
                'reference' => $row->reference,
                'chain' => $row->chain,
                'currency' => $row->currency,
                'amount' => $batch['amount'] ?? $row->expected_amount,
                'batch_asset_count' => $batch['count'] ?? 1,
                'user' => $row->user ? [
                    'id' => $row->user->id,
                    'name' => $row->user->name,
                    'email' => $row->user->email,
                ] : null,
                'payload_ref' => $row->received_asset_id,
                'type' => $row->type,
                'created_at' => $row->created_at,
            ];
        });
    }

    public function getAvaialbleAsset()
    {
        $assets = ReceivedAsset::with('user')->latest()->get();
        return $assets;
    }

    /**
     * Rejected / fake deposit webhooks (not credited to user balance).
     */
    public function getRejectedDeposits(Request $request)
    {
        $q = RejectedDepositWebhook::query()->with('user:id,email,name');

        if ($request->filled('rejection_reason')) {
            $q->where('rejection_reason', $request->string('rejection_reason'));
        }
        if ($request->filled('tx_id')) {
            $q->where('tx_id', $request->string('tx_id'));
        }
        if ($request->filled('to_address')) {
            $q->where('to_address', 'like', '%'.$request->string('to_address').'%');
        }
        if ($request->filled('contract_address')) {
            $q->whereRaw('LOWER(contract_address) = ?', [strtolower($request->string('contract_address'))]);
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->input('user_id'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $paginated = $q->latest('id')->paginate($perPage);

        return ResponseHelper::success($paginated, 'Rejected deposit webhooks fetched', 200);
    }
    public function setAdminTransfer(Request $request)
    {
        $blockchain = $request->input('blockchain');
        $currency = $request->input('currency');
        $address = $request->input('address');
        $forAll = $request->input('forAll');
        $data = [
            'blockchain' => $blockchain,
            'currency' => $currency,
            'address' => $address,
            'forAll' => $forAll
        ];
        $AdminTransfer = new AdminTransfer();
        $AdminTransfer->blockchain = $data['blockchain'];
        $AdminTransfer->currency = $data['currency'];
        $AdminTransfer->address = $data['address'];
        $AdminTransfer->forAll = $data['forAll'];
        $AdminTransfer->save();
        return response()->json(['message' => 'Admin transfer created successfully', 'data' => $AdminTransfer]);
    }
    public function getAdminTransfer()
    {
        $adminTransfers = AdminTransfer::all();
        return response()->json($adminTransfers);
    }
    public function setIndividualTransfer($id, Request $request)
    {
        $asset = ReceivedAsset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }
        $asset->transfer_address = $request->transfer_address;
        $asset->save();
        return response()->json(['message' => 'Transfer address updated successfully', 'data' => $asset]);
    }
}
