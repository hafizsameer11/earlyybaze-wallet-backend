<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'otp',
        'invite_code',
        'user_code',
        'profile_picture',
        'role',
        'kyc_status',
        'is_freezon',
        'fullName',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
    'two_factor_recovery_codes' => 'array',
    'two_factor_confirmed_at' => 'datetime',
    ];
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function resetPassword()
    {
        return $this->hasMany(ResetPassword::class);
    }
    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class);
    }
    public function nairaWallet()
    {
        return $this->hasOne(NairaWallet::class);
    }
    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }
    public function userAccount()
    {
        return $this->hasOne(UserAccount::class);
    }
    public function kyc()
    {
        return $this->hasOne(Kyc::class);
    }
    public function newsletters()
    {
        return $this->belongsToMany(Newsletter::class, 'user_newsletters')->withTimestamps()->withPivot('is_read', 'sent_at');
    }
    public function userActivity()
    {
        return $this->hasMany(UserActivity::class);
    }
}
