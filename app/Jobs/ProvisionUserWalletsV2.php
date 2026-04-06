<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\UserWalletV2Provisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionUserWalletsV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function handle(UserWalletV2Provisioner $provisioner): void
    {
        try {
            Log::info('Wallet v2 provisioning started', ['user_id' => $this->user->id]);
            $provisioner->provision($this->user->fresh());
        } catch (\Throwable $e) {
            Log::error('ProvisionUserWalletsV2 failed: '.$e->getMessage(), ['user_id' => $this->user->id]);
            throw $e;
        }
    }
}
