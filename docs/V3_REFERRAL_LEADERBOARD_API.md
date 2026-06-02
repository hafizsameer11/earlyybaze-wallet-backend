# V3 Referral Leaderboard API

New endpoint only — legacy referral routes are unchanged.

## Endpoint

```
GET /api/v3/referral/leaderboard
```

**Auth:** Bearer token (same as other V3 user routes)

## Query parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `period` | string | `weekly` | `weekly`, `monthly`, or `all_time` (aliases: `week`, `month`, `all`) |
| `limit` | int | `50` | Max ranked users returned (10–100) |

## Example

```http
GET /api/v3/referral/leaderboard?period=weekly&limit=50
Authorization: Bearer {token}
```

## Success response (200)

```json
{
  "status": "success",
  "message": "Leaderboard fetched successfully",
  "data": {
    "period": "weekly",
    "period_label": "Weekly",
    "total_ranked": 10,
    "top_three": [
      {
        "rank": 1,
        "user_id": 12,
        "username": "@kingsley_p",
        "display_name": "Kingsley",
        "avatar_initial": "K",
        "avatar_color": "#22C55E",
        "tier": "Gold",
        "tier_label": "Gold tier",
        "active_referrals": 80,
        "earnings_usd": 18420.5,
        "is_current_user": false
      }
    ],
    "list": [
      {
        "rank": 4,
        "user_id": 5,
        "username": "@seyi.x",
        "display_name": "Seyi",
        "avatar_initial": "S",
        "avatar_color": "#3B82F6",
        "tier": "Gold",
        "tier_label": "Gold tier",
        "active_referrals": 90,
        "earnings_usd": 7120.1,
        "is_current_user": false
      }
    ],
    "rankings": [],
    "current_user": {
      "rank": 7,
      "user_id": 99,
      "username": "@you",
      "display_name": "Qamardeen",
      "avatar_initial": "Q",
      "avatar_color": "#F59E0B",
      "tier": "Bronze",
      "tier_label": "Bronze tier",
      "active_referrals": 3,
      "earnings_usd": 4280.55,
      "is_current_user": true
    },
    "current_user_id": 99
  }
}
```

## Ranking rules

- **Earnings:** Sum of `referal_earnings.amount` (USD) per referrer (`user_id`) in the selected period.
- **Weekly:** `created_at >= start of current week`
- **Monthly:** `created_at >= start of current month`
- **All-time:** no date filter
- **Tier:** Based on **all-time** active referrals (distinct `referal_id` with at least one `swap_transaction_id` earning):
  - Bronze: 0+
  - Silver: 25+
  - Gold: 75+
  - Platinum: 200+

## Empty leaderboard

When no earners exist for the period:

```json
{
  "status": "success",
  "data": {
    "period": "weekly",
    "total_ranked": 0,
    "top_three": [],
    "list": [],
    "rankings": [],
    "current_user": null
  }
}
```

## Mobile mapping

| UI | API field |
|----|-----------|
| Weekly / Monthly / All-time tabs | `period` query param |
| Podium (1st center, 2nd left, 3rd right) | `top_three[0]`, `[1]`, `[2]` |
| Rows 4+ | `list` |
| Highlight “YOU” row | `is_current_user` or `current_user` |
| Earnings | `earnings_usd` → format as `$X,XXX.XX` |
| Tier badge | `tier_label` |

## Related V3 referral routes (unchanged)

- `GET /api/v3/referral/summary`
- `GET /api/v3/referral/transfer-options`
- `POST /api/v3/referral/transfer`
