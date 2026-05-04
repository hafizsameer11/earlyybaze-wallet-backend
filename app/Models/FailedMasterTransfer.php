<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class FailedMasterTransfer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'virtual_account_id',
        'webhook_response_id',
        'reason',
    ];
}
