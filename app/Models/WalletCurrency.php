<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletCurrency extends Model
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
        'contract_address'
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
