<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterWallet extends Model
{
    use HasFactory;
    protected $fillable = [
        'blockchain',
        'xpub',
        'address',
        'private_key',
        'mnemonic',
    ];
}
