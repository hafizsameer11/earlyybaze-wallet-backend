<?php

namespace App\Services;

use App\Jobs\ProvisionUserWalletsV2;
use App\Models\User;

class EnsureV2WalletsProvisioned
{
    /**
     * Dispatch v2 wallet provisioning for legacy users (wallet_flow_version v1) when they hit balance/assets.
     */
    public static function dispatchIfNeeded(?User $user): void
    {
        if (! $user || $user->wallet_flow_version !== 'v1') {
            return;
        }

        dispatch(new ProvisionUserWalletsV2($user, 'lazy_balance'));
    }
}
