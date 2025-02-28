<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;
    protected $fillable = [
        'currency_id',
        'rate',
        'currency',
        'status'
    ];
    public function currency()
    {
        return $this->belongsTo(WalletCurrency::class, 'currency_id');
    }
}
