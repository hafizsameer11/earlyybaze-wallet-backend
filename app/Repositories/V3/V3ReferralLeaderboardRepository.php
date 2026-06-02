<?php

namespace App\Repositories\V3;

use App\Models\ReferalEarning;
use App\Models\User;
use App\Support\ReferralTierHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class V3ReferralLeaderboardRepository
{
    public function getLeaderboard(int $currentUserId, string $period, int $limit = 50): array
    {
        $period = $this->normalizePeriod($period);
        $since = $this->periodStart($period);

        $earningsQuery = ReferalEarning::query()
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as earnings_usd'))
            ->groupBy('user_id')
            ->having('earnings_usd', '>', 0);

        if ($since !== null) {
            $earningsQuery->where('created_at', '>=', $since);
        }

        $rows = $earningsQuery
            ->orderByDesc('earnings_usd')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return $this->buildPayload($period, [], null, $currentUserId);
        }

        $userIds = $rows->pluck('user_id')->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');
        $activeCounts = $this->activeReferralCounts($userIds);

        $rankings = [];
        $rank = 1;
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $user = $users->get($uid);
            $active = (int) ($activeCounts[$uid] ?? 0);
            $tier = ReferralTierHelper::tierForActiveCount($active);

            $rankings[] = $this->formatEntry(
                $rank,
                $uid,
                $user,
                (float) $row->earnings_usd,
                $active,
                $tier,
                $uid === $currentUserId
            );
            $rank++;
        }

        $currentUserEntry = collect($rankings)->firstWhere('is_current_user', true);
        if (! $currentUserEntry) {
            $currentUserEntry = $this->resolveCurrentUserRank($currentUserId, $period, $since);
        }

        return $this->buildPayload($period, $rankings, $currentUserEntry, $currentUserId);
    }

    private function resolveCurrentUserRank(int $userId, string $period, ?Carbon $since): ?array
    {
        $query = ReferalEarning::query()->where('user_id', $userId);
        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }
        $earnings = (float) $query->sum('amount');
        if ($earnings <= 0) {
            return null;
        }

        $higherQuery = ReferalEarning::query()
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as earnings_usd'))
            ->groupBy('user_id')
            ->having('earnings_usd', '>', $earnings);

        if ($since !== null) {
            $higherQuery->where('created_at', '>=', $since);
        }

        $rank = (int) $higherQuery->count() + 1;
        $user = User::find($userId);
        $active = (int) ($this->activeReferralCounts([$userId])[$userId] ?? 0);
        $tier = ReferralTierHelper::tierForActiveCount($active);

        return $this->formatEntry($rank, $userId, $user, $earnings, $active, $tier, true);
    }

    /**
     * @param  array<int>  $userIds
     * @return array<int, int>
     */
    private function activeReferralCounts(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return ReferalEarning::query()
            ->select('user_id', DB::raw('COUNT(DISTINCT referal_id) as active_count'))
            ->whereIn('user_id', $userIds)
            ->whereNotNull('swap_transaction_id')
            ->groupBy('user_id')
            ->pluck('active_count', 'user_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    private function formatEntry(
        int $rank,
        int $userId,
        ?User $user,
        float $earningsUsd,
        int $activeReferrals,
        array $tier,
        bool $isCurrentUser
    ): array {
        $name = trim((string) ($user?->name ?? 'User'));
        $slug = $this->usernameSlug($name, $userId);

        return [
            'rank' => $rank,
            'user_id' => $userId,
            'username' => '@'.$slug,
            'display_name' => $name !== '' ? $name : 'User',
            'avatar_initial' => strtoupper(substr($name !== '' ? $name : 'U', 0, 1)),
            'avatar_color' => $this->avatarColor($userId),
            'tier' => $tier['id'],
            'tier_label' => $tier['label'],
            'active_referrals' => $activeReferrals,
            'earnings_usd' => round($earningsUsd, 2),
            'is_current_user' => $isCurrentUser,
        ];
    }

    private function buildPayload(string $period, array $rankings, ?array $currentUser, int $currentUserId): array
    {
        $topThree = array_slice($rankings, 0, 3);
        $list = array_slice($rankings, 3);

        return [
            'period' => $period,
            'period_label' => match ($period) {
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                default => 'All-time',
            },
            'total_ranked' => count($rankings),
            'top_three' => $topThree,
            'list' => $list,
            'rankings' => $rankings,
            'current_user' => $currentUser,
            'current_user_id' => $currentUserId,
        ];
    }

    private function normalizePeriod(string $period): string
    {
        $p = strtolower(trim($period));

        return match ($p) {
            'week', 'weekly' => 'weekly',
            'month', 'monthly' => 'monthly',
            'all', 'all_time', 'alltime', 'all-time' => 'all_time',
            default => 'weekly',
        };
    }

    private function periodStart(string $period): ?Carbon
    {
        return match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default => null,
        };
    }

    private function usernameSlug(string $name, int $userId): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? '');
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'user_'.$userId;
    }

    private function avatarColor(int $userId): string
    {
        $palette = ['#22C55E', '#3B82F6', '#8B5CF6', '#F59E0B', '#EC4899', '#14B8A6', '#6366F1'];

        return $palette[$userId % count($palette)];
    }
}
