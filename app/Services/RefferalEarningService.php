<?php

namespace App\Services;

use App\Repositories\RefferalEarningRepository;

class RefferalEarningService
{
    protected $RefferalEarningRepository;

    public function __construct(RefferalEarningRepository $RefferalEarningRepository)
    {
        $this->RefferalEarningRepository = $RefferalEarningRepository;
    }

    public function all()
    {
        return $this->RefferalEarningRepository->all();
    }

    public function find($id)
    {
        return $this->RefferalEarningRepository->find($id);
    }
    public function getByUserId($userId)
    {
        try {
            return $this->RefferalEarningRepository->getForUser($userId);
        } catch (\Exception $e) {
            throw new \Exception('Data not found');
        }
    }

    public function create(array $data)
    {
        return $this->RefferalEarningRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->RefferalEarningRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->RefferalEarningRepository->delete($id);
    }
}
