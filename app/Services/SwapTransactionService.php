<?php

namespace App\Services;

use App\Repositories\SwapTransactionRepository;

class SwapTransactionService
{
    protected $SwapTransactionRepository;

    public function __construct(SwapTransactionRepository $SwapTransactionRepository)
    {
        $this->SwapTransactionRepository = $SwapTransactionRepository;
    }
    public function swap(array $data)
    {
        try {
            return $this->SwapTransactionRepository->swap($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
