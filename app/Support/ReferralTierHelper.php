<?php

namespace App\Support;

/**
 * Mirrors mobile referral tier thresholds (active referrals with ≥1 swap).
 */
class ReferralTierHelper
{
    private const TIERS = [
        ['id' => 'Bronze', 'min_active' => 0, 'label' => 'Bronze tier'],
        ['id' => 'Silver', 'min_active' => 25, 'label' => 'Silver tier'],
        ['id' => 'Gold', 'min_active' => 75, 'label' => 'Gold tier'],
        ['id' => 'Platinum', 'min_active' => 200, 'label' => 'Platinum tier'],
    ];

    public static function tierForActiveCount(int $activeCount): array
    {
        $current = self::TIERS[0];
        foreach (self::TIERS as $tier) {
            if ($activeCount >= $tier['min_active']) {
                $current = $tier;
            }
        }

        return $current;
    }
}
