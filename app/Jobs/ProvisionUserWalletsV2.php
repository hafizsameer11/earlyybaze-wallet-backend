<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WalletV2ProvisionLog;
use App\Services\UserWalletV2Provisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionUserWalletsV2 implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $trigger = 'otp',
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(UserWalletV2Provisioner $provisioner): void
    {
        $user = $this->user->fresh();
        if (! $user) {
            return;
        }

        $wasV1 = $user->wallet_flow_version === 'v1';

        try {
            Log::info('Wallet v2 provisioning started', [
                'user_id' => $user->id,
                'trigger' => $this->trigger,
            ]);

            $provisioner->provision($user);

            $user = $user->fresh();
            if ($wasV1 && $user && $user->wallet_flow_version === 'v1') {
                $user->wallet_flow_version = 'v2';
                $user->save();
            }

            WalletV2ProvisionLog::query()->create([
                'user_id' => $user->id,
                'job_type' => 'ProvisionUserWalletsV2',
                'trigger' => $this->trigger,
                'status' => 'success',
                'error_message' => null,
                'error_json' => null,
                'raw_error' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProvisionUserWalletsV2 failed: '.$e->getMessage(), [
                'user_id' => $this->user->id,
                'trigger' => $this->trigger,
            ]);

            WalletV2ProvisionLog::query()->create([
                'user_id' => $this->user->id,
                'job_type' => 'ProvisionUserWalletsV2',
                'trigger' => $this->trigger,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'error_json' => [
                    'exception' => get_class($e),
                    'code' => $e->getCode(),
                ],
                'raw_error' => (string) $e,
                'created_at' => now(),
            ]);

            throw $e;
        }
    }
}
