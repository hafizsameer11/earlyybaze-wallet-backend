<?php

namespace App\Services;

use App\Repositories\SupportTicketRepository;

class SupportTicketService
{
    protected $SupportTicketRepository;

    public function __construct(SupportTicketRepository $SupportTicketRepository)
    {
        $this->SupportTicketRepository = $SupportTicketRepository;
    }

    public function all()
    {
        try {
            return $this->SupportTicketRepository->all();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function find($id)
    {
        try {
            return $this->SupportTicketRepository->find($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            return $this->SupportTicketRepository->create($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function getAllforUser($userId)
    {
        try {
            return $this->SupportTicketRepository->getAllforUser($userId);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function markResolved($id)
    {
        try {
            return $this->SupportTicketRepository->markResolved($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function update($id, array $data)
    {
        return $this->SupportTicketRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->SupportTicketRepository->delete($id);
    }
    public function assignToAgent($data)
    {
        return $this->SupportTicketRepository->assignToAgent($data);
    }
}
