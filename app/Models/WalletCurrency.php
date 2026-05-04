<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletCurrency extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'blockchain',
        'currency',
        'naira_price',
        'price',
        'symbol',
        'decimals',
        'token_type',
        'contract_address',
        'is_token',
    ];

    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class);
    }

    public function exchangeRates()
    {
        return $this->hasMany(ExchangeRate::class);
    }
}
