<?php

namespace App\Console\Commands;

use App\Models\ReceivedAsset;
use App\Services\FlushBatchExpectations;
use App\Services\FlushCompletionService;
use App\Services\TatumOnChainTxVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConfirmPendingFlushTransactionsCommand extends Command
{
    protected $signature = 'wallet:confirm-pending-flushes {--days=3 : Look back window in days} {--dry-run : Report only}';

    protected $description = 'Re-check broadcast flush txs awaiting on-chain confirmation and complete verified assets';

    public function handle(
        TatumOnChainTxVerifier $verifier,
        FlushCompletionService $completionService,
        FlushBatchExpectations $batchExpectations,
    ): int {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $assets = ReceivedAsset::query()
            ->whereNotNull('transfered_tx')
            ->where('status', '!=', 'completed')
            ->whereIn('flush_status', ['pending', 'confirming', 'failed'])
            ->where('updated_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No pending flush confirmations to check.');

            return self::SUCCESS;
        }

        $groups = $assets->groupBy(fn (ReceivedAsset $a) => $a->transfered_tx.'|'.$a->currency);
        $confirmed = 0;
        $stillPending = 0;

        foreach ($groups as $group) {
            /** @var ReceivedAsset $first */
            $first = $group->first();
            $txId = (string) $first->transfered_tx;
            $currency = (string) $first->currency;
            $batch = $batchExpectations->resolveFromTx($txId, $currency);
            $expectedFrom = $batch['expected_from'] ?? ($first->transfer_address ?: null);
            $expectedTo = $batch['expected_to'] !== '' ? $batch['expected_to'] : (string) ($first->address_to_send ?? '');
            $expectedAmount = $batch['expected_amount'] ?? number_format(
                (float) $group->sum(fn (ReceivedAsset $a) => (float) ($a->transfered_amount ?: $a->amount)),
                8,
                '.',
                ''
            );
            $ids = ($batch['pending_asset_ids'] ?? []) !== [] ? $batch['pending_asset_ids'] : $group->pluck('id')->all();

            $result = $verifier->verifyFlush(
                $currency,
                $txId,
                $expectedFrom,
                $expectedTo,
                $expectedAmount,
            );

            if ($result->isSuccess()) {
                $confirmed++;
                $this->info("Confirmed flush {$txId} ({$currency}) — {$group->count()} asset(s)");

                if (! $dryRun) {
                    $completionService->completeVerifiedFlush(
                        $ids,
                        $currency,
                        $txId,
                        $expectedFrom,
                        $expectedTo,
                        $expectedAmount,
                        $first->gas_fee ? (float) $first->gas_fee : null,
                        $result,
                    );
                }

                continue;
            }

            if ($result->failureCode === \App\DTO\OnChainVerificationResult::FAIL_TX_UNCONFIRMED) {
                $stillPending++;
                if (! $dryRun) {
                    ReceivedAsset::whereIn('id', $ids)->update([
                        'flush_status' => 'confirming',
                        'verification_error' => $result->failureMessage,
                    ]);
                }
                $this->line("Still confirming {$txId} ({$currency})");

                continue;
            }

            $this->warn("Flush {$txId} ({$currency}) — {$result->failureCode}: {$result->failureMessage}");
        }

        $this->info(sprintf(
            'Pending flush check done: %d groups, %d confirmed, %d still confirming%s.',
            $groups->count(),
            $confirmed,
            $stillPending,
            $dryRun ? ' (dry run)' : ''
        ));

        Log::info('wallet:confirm-pending-flushes finished', [
            'groups' => $groups->count(),
            'confirmed' => $confirmed,
            'still_pending' => $stillPending,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
