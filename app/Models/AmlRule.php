<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AmlRule extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'transaction_type',
        'condition_operator',
        'amount',
        'time_frame',
        'trigger_count',
        'action',
        'action_message',
        'status',
    ];
}
