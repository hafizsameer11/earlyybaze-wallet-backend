<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnChainTransferLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'tx',
        'received_asset_id',
        'gas_fee',
        'address_to_send'
    ];
    public function asset(){
        return $this->belongsTo(ReceivedAsset::class,'received_asset_id');
    }
}
