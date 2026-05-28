<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExchangeRate extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'currency_id',
        'rate',
        'currency',
        'status',
        'rate_naira',
        'rate_zar',
        'rate_usd',
    ];

    public function currency()
    {
        return $this->belongsTo(WalletCurrency::class, 'currency_id');
    }
}
