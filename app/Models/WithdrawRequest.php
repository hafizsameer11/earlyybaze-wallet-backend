<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← Import trait

class WithdrawRequest extends Model
{
    use HasFactory, SoftDeletes; // ← Apply SoftDeletes

    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'reference',
        'fee',
        'total',
        'asset',
        'bank_account_id',
        'bank_account_name',
        'bank_account_code',
        'account_name',
        'account_number',
        'send_account',
        'balance_before',
    ];

    /**
     * Fields to hide from JSON response - we'll use formatted bank_account instead
     */
    protected $hidden = [
        // Don't hide these, let repository handle transformation
    ];

    // 🧠 When fetching balance_before, auto-calculate if missing
    public function getBalanceBeforeAttribute($value)
    {
        // If already set, return it
        if (!is_null($value)) {
            return $value;
        }

        // Otherwise calculate dynamically
        $userAccount = \App\Models\UserAccount::where('user_id', $this->user_id)->first();

        if ($userAccount && $this->total) {
            // Reverse calculate balance before
            return $userAccount->naira_balance + $this->total;
        }

        // Fallback default (if no account found)
        return 0;
    }

    /**
     * Get formatted bank account object - uses relationship if bank_account_id exists,
     * otherwise uses direct fields (bank_account_name, bank_account_code, account_name, account_number)
     * Always returns in consistent format regardless of source
     * 
     * @return array|null
     */
    public function getFormattedBankAccount()
    {
        // Priority 1: If bank_account_id exists, use the relationship (old implementation)
        if (!is_null($this->bank_account_id)) {
            // Load relationship if not already loaded
            if (!$this->relationLoaded('bankAccount')) {
                $this->load('bankAccount');
            }
            
            if ($this->bankAccount) {
                return [
                    'id' => $this->bankAccount->id,
                    'user_id' => $this->bankAccount->user_id,
                    'account_number' => $this->bankAccount->account_number,
                    'account_name' => $this->bankAccount->account_name,
                    'bank_name' => $this->bankAccount->bank_name,
                    'bank_account_code' => null, // Not in old BankAccount model
                    'is_default' => $this->bankAccount->is_default ?? false,
                    'created_at' => $this->bankAccount->created_at?->toDateTimeString(),
                    'updated_at' => $this->bankAccount->updated_at?->toDateTimeString(),
                ];
            }
            // If bank_account_id exists but relationship is null (orphaned), fall through to direct fields
        }
        
        // Priority 2: If bank_account_id doesn't exist (or relationship failed), use direct fields (new implementation)
        if (!is_null($this->bank_account_name) || 
            !is_null($this->account_name) || 
            !is_null($this->account_number)) {
            return [
                'id' => $this->bank_account_id, // Keep the ID if it exists (even if orphaned)
                'user_id' => $this->user_id,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
                'bank_name' => $this->bank_account_name,
                'bank_account_code' => $this->bank_account_code,
                'is_default' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }
        
        // No bank account data available
        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
