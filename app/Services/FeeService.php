<?php

namespace App\Services;

use App\Repositories\FeeRepository;
use Exception;

class FeeService
{
    protected $FeeRepository;

    public function __construct(FeeRepository $FeeRepository)
    {
        $this->FeeRepository = $FeeRepository;
    }

    public function all()
    {
        return $this->FeeRepository->all();
    }

    public function find($id)
    {
        return $this->FeeRepository->find($id);
    }

    public function create(array $data)
    {
        try {
            return $this->FeeRepository->create($data);
        } catch (Exception $e) {
            throw new Exception('Fee Creation Failed ' . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->FeeRepository->update($id, $data);
        } catch (Exception $e) {
            throw new Exception('Fee Update Failed ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        return $this->FeeRepository->delete($id);
    }
    public function getByType($type)
    {
        try {
            return $this->FeeRepository->getByType($type);
        } catch (Exception $e) {
            throw new Exception('Get Fee by type Failed' . $e->getMessage());
        }
        // return $this->FeeRepository->getByType($type);
    }
    public function getAll()
    {
        try {
            return $this->FeeRepository->all();
        } catch (Exception $e) {
            throw new Exception('Get All Fee Failed' . $e->getMessage());
        }
    }
}
