<?php

namespace App\Services;

use App\Models\ReceivedAsset;
use Illuminate\Support\Collection;

class FlushBatchExpectations
{
    /**
     * Resolve on-chain verification expectations for a broadcast flush tx.
     *
     * Batch flushes send one tx whose output amount is the sum of all row amounts;
     * per-row failure records must not be used for amount comparison.
     *
     * @return array{
     *     asset_ids: list<int>,
     *     pending_asset_ids: list<int>,
     *     expected_from: ?string,
     *     expected_to: string,
     *     expected_amount: string,
     *     asset_count: int,
     * }|null
     */
    public function resolveFromTx(string $txId, string $currency): ?array
    {
        $txId = trim($txId);
        if ($txId === '') {
            return null;
        }

        $assets = ReceivedAsset::query()
            ->where('transfered_tx', $txId)
            ->where('currency', $currency)
            ->orderBy('id')
            ->get();

        if ($assets->isEmpty()) {
            return null;
        }

        /** @var ReceivedAsset $first */
        $first = $assets->first();

        return [
            'asset_ids' => $assets->pluck('id')->all(),
            'pending_asset_ids' => $assets
                ->filter(fn (ReceivedAsset $a) => in_array($a->flush_status, ['pending', 'confirming', 'failed'], true))
                ->pluck('id')
                ->values()
                ->all(),
            'expected_from' => $first->transfer_address ?: null,
            'expected_to' => (string) ($first->address_to_send ?? ''),
            'expected_amount' => self::formatBatchAmount($assets),
            'asset_count' => $assets->count(),
        ];
    }

    /**
     * @param  Collection<int, ReceivedAsset>|iterable<ReceivedAsset>  $assets
     */
    public static function formatBatchAmount(iterable $assets): string
    {
        $sum = 0.0;
        foreach ($assets as $asset) {
            $sum += (float) ($asset->transfered_amount ?: $asset->amount);
        }

        return number_format($sum, 8, '.', '');
    }
}
