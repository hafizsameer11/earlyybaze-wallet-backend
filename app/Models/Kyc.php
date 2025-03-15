<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'dob',
        'bvn',
        'address',
        'state',
        'document_type',
        'document_number',
        'picture',
        'document_front',
        'document_back',
        'status'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
