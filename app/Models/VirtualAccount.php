<?php

namespace App\Models;

use App\Services\FiatBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VirtualAccount extends BaseModel
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
        'currency_id',
        'is_tatum_ledger',
    ];

    protected $casts = [
        'is_tatum_ledger' => 'boolean',
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

    /** Crypto/Tatum ledger accounts only — excludes fiat (ZAR, NGN) on user_accounts. */
    public function scopeCryptoOnly(Builder $query): Builder
    {
        return $query->whereNotIn('currency', ['ZAR', 'RAND', 'NGN', 'NAIRA']);
    }

    public static function isFiatCurrency(string $currency): bool
    {
        return FiatBalanceService::isLedgerFiat($currency);
    }

    public function getAvailableBalanceAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        // If value is in scientific notation (e.g., 1.5526E-4)
        if (stripos($value, 'e') !== false) {
            return number_format((float) $value, 8, '.', ''); // you can increase decimals if needed
        }

        // Otherwise return as normal
        return $value;
    }
}
