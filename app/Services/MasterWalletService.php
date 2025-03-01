<?php

namespace App\Services;

use App\Repositories\MasterWalletRepository;
use App\Services\TatumService;

class MasterWalletService
{
    protected $walletRepository;
    protected $tatumService;

    public function __construct(MasterWalletRepository $walletRepository, TatumService $tatumService)
    {
        $this->walletRepository = $walletRepository;
        $this->tatumService = $tatumService;
    }

    public function createMasterWallet(string $blockchain): array
    {
        // Generate wallet using Tatum API
        $walletData = $this->tatumService->createWallet($blockchain);

        $masterWallet = $this->walletRepository->create([
            'blockchain' => $blockchain,
            'xpub' => $walletData['xpub'] ?? null,
            'address' => $walletData['address'] ?? null,
            'private_key' => $walletData['privateKey'] ?? null,
            'mnemonic' => $walletData['mnemonic'] ?? null,
        ]);

        return $masterWallet->toArray();
    }

    public function getMasterWallets()
    {
        return $this->walletRepository->getAll();
    }
    
}
