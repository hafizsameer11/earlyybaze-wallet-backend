<?php

namespace App\Services\V3;

use App\Repositories\V3\V3SwapTransactionRepository;

class V3SwapTransactionService
{
    public function __construct(
        protected V3SwapTransactionRepository $repository,
    ) {}

    public function swap(array $data)
    {
        try {
            return $this->repository->swap($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function singleSwapTransaction($id)
    {
        try {
            return $this->repository->singleSwapTransaction($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function completeSwapTransaction($id)
    {
        try {
            return $this->repository->completeSwapTransaction($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
