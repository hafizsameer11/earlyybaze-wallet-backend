<?php

namespace App\Services\Admin;

use App\Models\SwapReversal;
use App\Models\SwapTransaction;
use App\Models\Transaction;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WithdrawRequest;
use App\Services\FiatBalanceService;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Facades\DB;

class AdminSwapReversalService
{
    public function preview(int $swapId): array
    {
        $swap = SwapTransaction::with(['user:id,name,email', 'transaction'])->find($swapId);
        if (! $swap) {
            throw new Exception('Swap transaction not found.');
        }

        $fiatCurrency = $this->fiatCurrencyForSwap($swap);
        $originalFiat = $this->originalFiatAmount($swap);
        $originalCrypto = (string) $swap->amount;
        $reversedFiat = (string) ($swap->reversed_fiat ?? '0');
        $reversedCrypto = (string) ($swap->reversed_crypto ?? '0');

        $remainingFiat = bcsub($originalFiat, $reversedFiat, 8);
        if (bccomp($remainingFiat, '0', 8) < 0) {
            $remainingFiat = '0';
        }
        $remainingCrypto = bcsub($originalCrypto, $reversedCrypto, 8);
        if (bccomp($remainingCrypto, '0', 8) < 0) {
            $remainingCrypto = '0';
        }

        $pendingWithdraws = WithdrawRequest::query()
            ->where('user_id', $swap->user_id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get(['id', 'reference', 'amount', 'total', 'fee', 'status', 'currency', 'created_at']);

        $userFiatBalance = app(FiatBalanceService::class)->getAvailableBalance((int) $swap->user_id, $fiatCurrency);

        $warnings = [];
        $blocked = false;
        $blockReason = null;

        if ($swap->status === 'cancelled' || $swap->status === 'reversed') {
            $blocked = true;
            $blockReason = 'already_reversed';
        }

        if ($swap->status === 'partially_reversed' && bccomp($remainingFiat, '0', 8) <= 0) {
            $blocked = true;
            $blockReason = 'already_fully_reversed';
        }

        if ($pendingWithdraws->isNotEmpty() && in_array($swap->status, ['completed', 'partially_reversed'], true)) {
            $blocked = true;
            $blockReason = 'pending_withdraw';
            $warnings[] = 'User has pending withdraw request(s). Reject them first, then retry the swap reversal.';
        }

        $isPendingSwap = $swap->status === 'pending';
        $canReverse = ! $blocked && ($isPendingSwap || in_array($swap->status, ['completed', 'partially_reversed'], true));

        $fiatToRecover = '0';
        $cryptoToReturn = '0';
        $isPartial = false;

        if ($canReverse && ! $isPendingSwap) {
            $fiatToRecover = bccomp($userFiatBalance, $remainingFiat, 8) >= 0
                ? $remainingFiat
                : $userFiatBalance;

            if (bccomp($fiatToRecover, '0', 8) <= 0) {
                $blocked = true;
                $blockReason = 'insufficient_fiat_balance';
                $canReverse = false;
                $warnings[] = 'User has no '.$fiatCurrency.' balance available to recover for this reversal.';
            } else {
                $cryptoToReturn = $this->cryptoForFiatRecovery($originalFiat, $originalCrypto, $fiatToRecover);
                if (bccomp($cryptoToReturn, $remainingCrypto, 8) > 0) {
                    $cryptoToReturn = $remainingCrypto;
                }

                $isPartial = bccomp($fiatToRecover, $remainingFiat, 8) < 0;
                if ($isPartial) {
                    $warnings[] = sprintf(
                        'User balance (%s %s) is less than the remaining swap fiat (%s %s). Only a partial reversal is possible.',
                        $userFiatBalance,
                        $fiatCurrency,
                        $remainingFiat,
                        $fiatCurrency,
                    );
                    $warnings[] = sprintf(
                        'System will recover %s %s and return %s %s using the original swap exchange proportion.',
                        $fiatToRecover,
                        $fiatCurrency,
                        $cryptoToReturn,
                        $swap->currency,
                    );
                }
            }
        }

        return [
            'can_reverse' => $canReverse,
            'blocked' => $blocked,
            'block_reason' => $blockReason,
            'is_pending_swap' => $isPendingSwap,
            'is_partial' => $isPartial,
            'warnings' => $warnings,
            'swap' => [
                'id' => $swap->id,
                'transaction_id' => $swap->transaction_id,
                'status' => $swap->status,
                'currency' => $swap->currency,
                'network' => $swap->network,
                'reference' => $swap->reference,
                'user' => $swap->user,
            ],
            'original' => [
                'fiat_currency' => $fiatCurrency,
                'fiat_amount' => $originalFiat,
                'crypto_amount' => $originalCrypto,
                'exchange_rate' => (string) ($swap->exchange_rate ?? ''),
            ],
            'already_reversed' => [
                'fiat_amount' => $reversedFiat,
                'crypto_amount' => $reversedCrypto,
            ],
            'remaining' => [
                'fiat_amount' => $remainingFiat,
                'crypto_amount' => $remainingCrypto,
            ],
            'user_fiat_balance' => $userFiatBalance,
            'pending_withdraw_requests' => $pendingWithdraws,
            'reversal_plan' => $isPendingSwap
                ? ['action' => 'cancel_pending', 'message' => 'Cancel pending swap without balance changes.']
                : [
                    'action' => $isPartial ? 'partial_reverse' : 'full_reverse',
                    'fiat_to_recover' => $fiatToRecover,
                    'crypto_to_return' => $cryptoToReturn,
                    'fiat_currency' => $fiatCurrency,
                    'crypto_currency' => $swap->currency,
                ],
        ];
    }

    public function execute(int $swapId, ?int $adminId = null, ?string $adminNote = null): array
    {
        $preview = $this->preview($swapId);
        if (! $preview['can_reverse']) {
            throw new Exception($preview['warnings'][0] ?? 'Swap cannot be reversed in its current state.');
        }

        return DB::transaction(function () use ($swapId, $adminId, $adminNote, $preview) {
            $swap = SwapTransaction::where('id', $swapId)->lockForUpdate()->firstOrFail();

            $hasPendingWithdraw = WithdrawRequest::where('user_id', $swap->user_id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingWithdraw && in_array($swap->status, ['completed', 'partially_reversed'], true)) {
                throw new Exception('User still has a pending withdraw request. Reject it first.');
            }

            if ($preview['is_pending_swap']) {
                return $this->cancelPendingSwap($swap, $adminId, $adminNote);
            }

            $plan = $preview['reversal_plan'];
            $fiatCurrency = $plan['fiat_currency'];
            $fiatToRecover = (string) $plan['fiat_to_recover'];
            $cryptoToReturn = (string) $plan['crypto_to_return'];

            $fiatService = app(FiatBalanceService::class);
            $balanceBefore = $fiatService->getAvailableBalance((int) $swap->user_id, $fiatCurrency);

            if (bccomp($balanceBefore, $fiatToRecover, 8) < 0) {
                throw new Exception('User fiat balance changed. Refresh preview and try again.');
            }

            $fiatService->deduct((int) $swap->user_id, $fiatCurrency, $fiatToRecover);

            $va = VirtualAccount::where('user_id', $swap->user_id)
                ->where('currency', $swap->currency)
                ->where('blockchain', $swap->network)
                ->lockForUpdate()
                ->firstOrFail();

            $va->available_balance = bcadd((string) $va->available_balance, $cryptoToReturn, 8);
            $va->save();

            $newReversedFiat = bcadd((string) ($swap->reversed_fiat ?? '0'), $fiatToRecover, 8);
            $newReversedCrypto = bcadd((string) ($swap->reversed_crypto ?? '0'), $cryptoToReturn, 8);
            $originalFiat = $this->originalFiatAmount($swap);

            $newStatus = bccomp($newReversedFiat, $originalFiat, 8) >= 0 ? 'reversed' : 'partially_reversed';

            $swap->reversed_fiat = $newReversedFiat;
            $swap->reversed_crypto = $newReversedCrypto;
            $swap->status = $newStatus;
            $swap->save();

            if ($swap->transaction_id) {
                Transaction::where('id', $swap->transaction_id)->update(['status' => $newStatus]);
            }

            $reversal = SwapReversal::create([
                'swap_transaction_id' => $swap->id,
                'user_id' => $swap->user_id,
                'admin_id' => $adminId,
                'reversal_type' => $preview['is_partial'] ? SwapReversal::TYPE_PARTIAL : SwapReversal::TYPE_FULL,
                'fiat_currency' => $fiatCurrency,
                'fiat_amount_recovered' => $fiatToRecover,
                'crypto_currency' => $swap->currency,
                'crypto_network' => $swap->network,
                'crypto_amount_returned' => $cryptoToReturn,
                'original_fiat_amount' => $originalFiat,
                'original_crypto_amount' => (string) $swap->amount,
                'exchange_rate_used' => (string) ($swap->exchange_rate ?? ''),
                'user_fiat_balance_before' => $balanceBefore,
                'admin_note' => $adminNote,
                'metadata' => ['preview' => $preview['reversal_plan']],
            ]);

            DB::afterCommit(function () use ($swap, $fiatCurrency, $fiatToRecover, $cryptoToReturn) {
                app(NotificationService::class)->notifyUser(
                    (int) $swap->user_id,
                    'Swap reversed',
                    'An admin reversed your swap. '.$fiatToRecover.' '.$fiatCurrency.' was recovered and '.$cryptoToReturn.' '.$swap->currency.' was returned to your wallet.',
                    'swap_reversed'
                );
            });

            return [
                'reversal' => $reversal->fresh(),
                'swap' => $swap->fresh(['user:id,name,email']),
                'message' => $preview['is_partial']
                    ? 'Partial swap reversal completed.'
                    : 'Swap reversal completed.',
            ];
        });
    }

    private function cancelPendingSwap(SwapTransaction $swap, ?int $adminId, ?string $adminNote): array
    {
        if ($swap->status !== 'pending') {
            throw new Exception('Only pending swaps can be cancelled.');
        }

        $swap->status = 'cancelled';
        $swap->save();

        if ($swap->transaction_id) {
            Transaction::where('id', $swap->transaction_id)->update(['status' => 'cancelled']);
        }

        $reversal = SwapReversal::create([
            'swap_transaction_id' => $swap->id,
            'user_id' => $swap->user_id,
            'admin_id' => $adminId,
            'reversal_type' => SwapReversal::TYPE_CANCEL_PENDING,
            'fiat_currency' => $this->fiatCurrencyForSwap($swap),
            'fiat_amount_recovered' => '0',
            'crypto_currency' => $swap->currency,
            'crypto_network' => $swap->network,
            'crypto_amount_returned' => '0',
            'original_fiat_amount' => $this->originalFiatAmount($swap),
            'original_crypto_amount' => (string) $swap->amount,
            'admin_note' => $adminNote,
            'metadata' => ['action' => 'cancel_pending'],
        ]);

        DB::afterCommit(function () use ($swap) {
            app(NotificationService::class)->notifyUser(
                (int) $swap->user_id,
                'Swap cancelled',
                'Your pending '.$swap->currency.' swap was cancelled by an administrator.',
                'swap_cancelled'
            );
        });

        return [
            'reversal' => $reversal,
            'swap' => $swap->fresh(['user:id,name,email']),
            'message' => 'Pending swap cancelled.',
        ];
    }

    private function fiatCurrencyForSwap(SwapTransaction $swap): string
    {
        $fiat = strtoupper((string) ($swap->fiat_currency ?? ''));

        return $fiat === 'ZAR' ? 'ZAR' : 'NGN';
    }

    private function originalFiatAmount(SwapTransaction $swap): string
    {
        if ($this->fiatCurrencyForSwap($swap) === 'ZAR') {
            return (string) ($swap->amount_zar ?? '0');
        }

        return (string) ($swap->amount_naira ?? '0');
    }

    private function cryptoForFiatRecovery(string $originalFiat, string $originalCrypto, string $fiatToRecover): string
    {
        if (bccomp($originalFiat, '0', 8) <= 0 || bccomp($fiatToRecover, '0', 8) <= 0) {
            return '0';
        }

        return bcmul(bcdiv($fiatToRecover, $originalFiat, 12), $originalCrypto, 8);
    }
}
