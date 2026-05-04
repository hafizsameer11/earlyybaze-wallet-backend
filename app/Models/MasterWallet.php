<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterWallet extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'blockchain',
        'xpub',
        'address',
        'private_key',
        'mnemonic',
        'response',
    ];
}
