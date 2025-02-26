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

    public function create(array $data)
    {
        return $this->SupportReplyRepository->create($data);
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