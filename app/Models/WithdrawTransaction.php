<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class WithdrawTransaction extends BaseModel
{
    use HasFactory;

    protected $fillable = ['withdraw_request_id', 'transaction_id'];

    public function withdraw_request()
    {
        return $this->belongsTo(WithdrawRequest::class, 'withdraw_request_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
