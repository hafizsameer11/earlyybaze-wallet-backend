<?php

namespace App\Services\V3;

use App\Repositories\V3\V3ReferralLeaderboardRepository;

class V3ReferralLeaderboardService
{
    public function __construct(
        private V3ReferralLeaderboardRepository $repository,
    ) {}

    public function getLeaderboard(int $userId, string $period, int $limit = 50): array
    {
        $limit = max(10, min(100, $limit));

        return $this->repository->getLeaderboard($userId, $period, $limit);
    }
}
