<?php

namespace App\Services;

use App\Repositories\KycRepository;
use Exception;

class KycService
{
    protected $KycRepository;

    public function __construct(KycRepository $KycRepository)
    {
        $this->KycRepository = $KycRepository;
    }

    public function all()
    {
        return $this->KycRepository->all();
    }

    public function find($id)
    {
        return $this->KycRepository->find($id);
    }

    public function create(array $data)
    {
        try {
            return $this->KycRepository->create($data);
        } catch (Exception $e) {
            throw new Exception('Kyc Creation Failed ' . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        return $this->KycRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->KycRepository->delete($id);
    }
    public function getKycForUser($userId)
    {
        try {
            return $this->KycRepository->getKycByUserId($userId);
        } catch (Exception $e) {
            throw new Exception('Get Kyc Failed ' . $e->getMessage());
        }
    }
}
