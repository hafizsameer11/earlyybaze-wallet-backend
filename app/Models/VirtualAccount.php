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
        'accounting_currency',
        'currency_id'
    ];
    public function depositAddresses()
    {
        return $this->hasMany(DepositAddress::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function walletCurrency()
    {
        return $this->belongsTo(WalletCurrency::class, 'currency_id', 'id');
    }
    public function getAvailableBalanceAttribute($value)
{
    if ($value === null) {
        return null;
    }

    // If value is in scientific notation (e.g., 1.5526E-4)
    if (stripos($value, 'e') !== false) {
        return number_format((float)$value, 8, '.', ''); // you can increase decimals if needed
    }

    // Otherwise return as normal
    return $value;
}

}
