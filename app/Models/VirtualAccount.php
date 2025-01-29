<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'blockchain',
        'currency',
        'customer_id',
        'account_id',
        'account_code',
        'active',
        'frozen',
        'account_balance',
        'available_balance',
        'xpub',
        'accounting_currency'
    ];
    public function depositAddresses()
    {
        return $this->hasMany(DepositAddress::class);
    }
}
