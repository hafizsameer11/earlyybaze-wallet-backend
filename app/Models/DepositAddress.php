<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositAddress extends Model
{
    use HasFactory;
    protected $fillable = [
        'virtual_account_id',
        'blockchain',
        'currency',
        'address',
    ];

    public function virtualAccount()
    {
        return $this->belongsTo(VirtualAccount::class);
    }
}
