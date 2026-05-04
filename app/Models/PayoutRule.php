<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayoutRule extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'trigger_event',
        'trade_amount',
        'time_frame',
        'action_type',
        'wallet_type',
        'payout_amount',
        'description',
        'status',
    ];
}
