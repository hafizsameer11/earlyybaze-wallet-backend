<?php

namespace App\Console\Commands;

use App\DTO\OnChainVerificationResult;
use App\Models\ReceivedAsset;
use App\Services\OnChainVerificationFailureRecorder;
use App\Services\TatumOnChainTxVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileOnChainTransactionsCommand extends Command
{
    protected $signature = 'wallet:reconcile-on-chain {--days=7 : Look back window in days} {--dry-run : Report only, do not mutate records}';

    protected $description = 'Re-verify recent completed flush transactions and flag dropped on-chain txs';

    public function handle(TatumOnChainTxVerifier $verifier): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $assets = ReceivedAsset::query()
            ->where('status', 'completed')
            ->whereNotNull('transfered_tx')
            ->where('updated_at', '>=', now()->subDays($days))
            ->where(function ($q) {
                $q->whereNull('flush_status')
                    ->orWhere('flush_status', 'verified');
            })
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No completed flush transactions to reconcile.');

            return self::SUCCESS;
        }

        $groups = $assets->groupBy(fn (ReceivedAsset $a) => $a->transfered_tx.'|'.$a->currency);
        $droppedGroups = 0;

        foreach ($groups as $group) {
            /** @var ReceivedAsset $first */
            $first = $group->first();
            $txId = (string) $first->transfered_tx;
            $currency = (string) $first->currency;
            $expectedFrom = $first->transfer_address ?: null;
            $expectedTo = (string) ($first->address_to_send ?? '');
            $expectedAmount = number_format((float) $group->sum(fn (ReceivedAsset $a) => (float) ($a->transfered_amount ?: $a->amount)), 8, '.', '');

            $result = $verifier->verifyFlush(
                $currency,
                $txId,
                $expectedFrom,
                $expectedTo,
                $expectedAmount,
            );

            if ($result->found) {
                continue;
            }

            $droppedGroups++;
            $this->warn("Dropped flush tx {$txId} ({$currency}) — {$result->failureMessage}");

            if ($dryRun) {
                continue;
            }

            $failure = new OnChainVerificationResult(
                found: false,
                confirmed: false,
                matches: false,
                failureCode: OnChainVerificationResult::FAIL_TX_DROPPED,
                failureMessage: 'Transaction no longer exists on chain (reconciliation)',
                raw: $result->raw,
            );

            DB::transaction(function () use ($group, $failure, $expectedFrom, $expectedTo, $expectedAmount) {
                foreach ($group as $asset) {
                    $asset->status = 'inWallet';
                    $asset->flush_status = 'failed';
                    $asset->verification_error = $failure->failureMessage;
                    $asset->save();

                    OnChainVerificationFailureRecorder::recordFlushFailure(
                        $asset,
                        $failure,
                        $expectedFrom,
                        $expectedTo,
                        $expectedAmount,
                    );
                }
            });
        }

        $this->info(sprintf(
            'Reconciliation finished: %d groups checked, %d dropped%s.',
            $groups->count(),
            $droppedGroups,
            $dryRun ? ' (dry run)' : ''
        ));

        return self::SUCCESS;
    }
}
