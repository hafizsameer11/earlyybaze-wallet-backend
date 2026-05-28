<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFraudProfile extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'risk_score',
        'open_alerts_count',
        'total_alerts_count',
        'last_alert_at',
        'risk_factors',
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'last_alert_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
