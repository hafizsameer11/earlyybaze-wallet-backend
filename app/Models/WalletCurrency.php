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
    ];
    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class);
    }
}
