<?php

namespace App\Services;

use App\Repositories\InAppBannerRepository;
use Exception;

class InAppBannerService
{
    protected $inAppBannerRepository;

    public function __construct(InAppBannerRepository $inAppBannerRepository)
    {
        $this->inAppBannerRepository = $inAppBannerRepository;
    }
    public function all()
    {
        return $this->inAppBannerRepository->all();
    }

    public function find($id)
    {
        return $this->inAppBannerRepository->find($id);
    }

    public function create(array $data)
    {
        try {
            return $this->inAppBannerRepository->create($data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->inAppBannerRepository->update($id, $data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return $this->inAppBannerRepository->delete($id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
