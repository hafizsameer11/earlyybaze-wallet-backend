<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedMasterTransfer extends Model
{
    use HasFactory;
    protected $fillable = [
        'virtual_account_id',
        'webhook_response_id',
    ];
}
