<?php

namespace App\Services;

use App\Repositories\SupportReplyRepository;

class SupportReplyService
{
    protected $SupportReplyRepository;

    public function __construct(SupportReplyRepository $SupportReplyRepository)
    {
        $this->SupportReplyRepository = $SupportReplyRepository;
    }

    public function all()
    {
        return $this->SupportReplyRepository->all();
    }

    public function find($id)
    {
        return $this->SupportReplyRepository->find($id);
    }

    public function createByUser(array $data, $userId)
    {
        try {
            return $this->SupportReplyRepository->createByUser($data,$userId);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function getAllByTicket($ticketId)
    {
       try {
            return $this->SupportReplyRepository->getAllByTicket($ticketId);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        return $this->SupportReplyRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->SupportReplyRepository->delete($id);
    }
}
