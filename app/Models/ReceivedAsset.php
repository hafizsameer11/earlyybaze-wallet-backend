<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivedAsset extends Model
{
    use HasFactory;
    protected $fillable = [
        'account_id',
        'subscription_type',
        'amount',
        'reference',
        'currency',
        'tx_id',
        'from_address',
        'to_address',
        'transaction_date',
        'status',
        'index',
        'user_id',
        'transfer_address',
        'transfered_tx',
        'transfered_amount',
        'gas_fee',
        'address_to_send',

    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
