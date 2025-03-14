<?php

namespace App\Services;

use App\Repositories\InAppNotificationRepository;
use Exception;

class InAppNotificationService
{
    protected $inAppNotificationRepository;

    public function __construct(InAppNotificationRepository $inAppNotificationRepository)
    {
        $this->inAppNotificationRepository = $inAppNotificationRepository;
    }

    public function all()
    {
        return $this->inAppNotificationRepository->all();
    }

    public function find($id)
    {
        return $this->inAppNotificationRepository->find($id);
    }

    public function create(array $data)
    {
        try {
            return $this->inAppNotificationRepository->create($data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->inAppNotificationRepository->update($id, $data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return $this->inAppNotificationRepository->delete($id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}