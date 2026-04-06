<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositAddress extends Model
{
    use HasFactory;
    protected $fillable = [
        'virtual_account_id',
        'version',
        'blockchain',
        'currency',
        'address',
        'index',
        'private_key',
        'tatum_v4_chain',
        'tatum_subscription_native_id',
        'tatum_subscription_fungible_id',
    ];

    public function virtualAccount()
    {
        return $this->belongsTo(VirtualAccount::class);
    }
}
