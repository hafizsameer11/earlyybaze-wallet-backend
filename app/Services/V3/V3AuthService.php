<?php

namespace App\Services\V3;

use App\Jobs\ProvisionUserWalletsV2;
use App\Mail\OtpMail;
use App\Models\User;
use App\Repositories\V3\V3AuthRepository;
use App\Services\NotificationService;
use App\Services\SentDmService;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class V3AuthService
{
    public function __construct(
        private V3AuthRepository $repository,
        private SentDmService $sentDmService,
    ) {}

    public function register(array $data): array
    {
        $data['password'] = Hash::make($data['password']);
        $data['otp'] = (string) random_int(100000, 999999);
        $data['sms_code'] = (string) random_int(100000, 999999);
        $data['sms_type'] = 'whatsapp';
        $data['is_number_verified'] = false;
        $data['wallet_flow_version'] = 'v3';
        $data['user_code'] = $this->generateUserCode($data['name']);
        $data['phone'] = $this->normalizePhone($data['phone']);

        $user = $this->repository->create($data);

        Mail::to($user->email)->send(new OtpMail($user->otp));
        $this->sendPhoneVerificationCode($user);

        $this->repository->createNairaWallet($user);
        $this->repository->createUserAccount($user, $this->generateAccountNumber());

        app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'Welcome to EarlyBaze',
            'Check your email and phone for verification codes.',
            'auth'
        );

        return $this->publicUserPayload($user, [
            'email_verification_required' => true,
            'phone_verification_required' => true,
        ]);
    }

    public function verifyEmailOtp(array $data): array
    {
        $user = $this->findPendingV3User($data['email']);

        if ($user->otp === null) {
            throw new Exception('Email already verified.');
        }

        if ($user->otp !== $data['otp']) {
            throw new Exception('Invalid email OTP.');
        }

        $user->otp = null;
        $user->otp_verified = true;
        $user->save();

        dispatch(new ProvisionUserWalletsV2($user, 'v3-email-otp'));

        app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'Email verified',
            'Your email is verified. Verify your phone number to complete setup.',
            'auth'
        );

        return $this->publicUserPayload($user, [
            'email_verified' => true,
            'phone_verification_required' => ! $user->is_number_verified,
        ]);
    }

    public function verifyPhoneOtp(array $data): array
    {
        $user = $this->findV3User($data['email']);

        if (! $user->otp_verified) {
            throw new Exception('Verify your email OTP first.');
        }

        if ($user->is_number_verified) {
            throw new Exception('Phone number already verified.');
        }

        if ($user->sms_code !== $data['otp']) {
            throw new Exception('Invalid phone verification code.');
        }

        $user->sms_code = null;
        $user->is_number_verified = true;
        $user->save();

        app(NotificationService::class)->notifyUser(
            (int) $user->id,
            'Phone verified',
            'Your phone number has been verified successfully.',
            'auth'
        );

        return $this->publicUserPayload($user, [
            'phone_verified' => true,
            'phone_verification_required' => false,
        ]);
    }

    public function resendEmailOtp(string $email): void
    {
        $user = $this->findPendingV3User($email);

        if ($user->otp === null) {
            throw new Exception('Email OTP already verified.');
        }

        $user->otp = (string) random_int(100000, 999999);
        $user->save();
        Mail::to($user->email)->send(new OtpMail($user->otp));
    }

    public function sendPhoneCode(string $email): void
    {
        $user = $this->findV3User($email);

        if (! $user->otp_verified) {
            throw new Exception('Verify your email OTP first.');
        }

        if ($user->is_number_verified) {
            throw new Exception('Phone number already verified.');
        }

        $user->sms_code = (string) random_int(100000, 999999);
        $user->save();

        $this->sendPhoneVerificationCode($user);
    }

    public function login(array $data): array
    {
        $user = $this->repository->findByEmail($data['email']);

        if (! $user) {
            throw new Exception('User not found.');
        }

        if ($user->wallet_flow_version !== 'v3') {
            throw new Exception('Use the standard login endpoint for this account.');
        }

        if (! $user->otp_verified) {
            throw new Exception('Email OTP verification required.');
        }

        if (! Hash::check($data['password'], $user->password)) {
            throw new Exception('Invalid password.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $virtualAccounts = $user->virtualAccounts()
            ->select(['id', 'currency', 'blockchain', 'currency_id', 'available_balance', 'account_balance'])
            ->get();

        $virtualAccounts->each(function ($account) {
            $account->walletCurrency = $account->walletCurrency()
                ->select(['id', 'price', 'symbol', 'naira_price'])
                ->first();
        });

        $phoneVerificationRequired = ! $user->is_number_verified;

        return [
            'user' => $user->only(['id', 'email', 'phone', 'wallet_flow_version']),
            'virtual_accounts' => $virtualAccounts,
            'token' => $token,
            'is_number_verified' => (bool) $user->is_number_verified,
            'phone_verification_required' => $phoneVerificationRequired,
        ];
    }

    private function findV3User(string $email): User
    {
        $user = $this->repository->findV3UserByEmail($email);

        if (! $user) {
            throw new Exception('V3 account not found for this email.');
        }

        return $user;
    }

    private function findPendingV3User(string $email): User
    {
        return $this->findV3User($email);
    }

    private function sendPhoneVerificationCode(User $user): void
    {
        $sent = $this->sentDmService->sendWhatsAppVerification(
            $user->phone,
            (string) $user->sms_code
        );

        if (! $sent) {
            Log::warning('V3 phone verification via Sent.dm failed', [
                'user_id' => $user->id,
                'phone' => $user->phone,
            ]);
        }
    }

    private function generateUserCode(string $username): string
    {
        do {
            $userCode = $username.'-'.random_int(100000, 999999);
        } while ($this->repository->findByUserCode($userCode));

        return $userCode;
    }

    private function generateAccountNumber(): string
    {
        return 'EarlyBaze-'.random_int(1000000000, 9999999999);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '234')) {
            return '+'.substr($digits, 0, 13);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+234'.$digits;
        }

        if (str_starts_with($digits, '27')) {
            return '+'.$digits;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        throw new Exception('Invalid phone number. Use Nigerian format (e.g. 08012345678).');
    }

    private function publicUserPayload(User $user, array $extra = []): array
    {
        return array_merge(
            $user->only(['id', 'name', 'email', 'phone', 'wallet_flow_version']),
            $extra
        );
    }
}
