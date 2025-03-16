<?php

namespace App\Services;

use App\Repositories\WalletManagementRepository;

class WalletManagementService
{
    protected $WalletManagementRepository;

    public function __construct(WalletManagementRepository $WalletManagementRepository)
    {
        $this->WalletManagementRepository = $WalletManagementRepository;
    }

    public function all()
    {
        return $this->WalletManagementRepository->all();
    }

    public function find($id)
    {
        return $this->WalletManagementRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->WalletManagementRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->WalletManagementRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->WalletManagementRepository->delete($id);
    }
    public function getVirtualWalletsData()
    {
        try {
            $data = $this->WalletManagementRepository->getVirtualWalletsData();
            return $data;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
