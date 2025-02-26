<?php

namespace App\Repositories;

use App\Models\Kyc;
use Illuminate\Support\Facades\Auth;

class KycRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }

    public function create(array $data)
    {
        // Add logic to create data
        $user = Auth::user();
        $data['user_id'] = $user->id;
        if (isset($data['picture']) && $data['picture']) {
            $path = $data['picture']->store('picture', 'public');
            $data['picture'] = $path;
        }
        if (isset($data['document_front']) && $data['document_front']) {
            $path = $data['document_front']->store('document_front', 'public');
            $data['document_front'] = $path;
        }
        if (isset($data['document_back']) && $data['document_back']) {
            $path = $data['document_back']->store('document_back', 'public');
            $data['document_back'] = $path;
        }
        return Kyc::create($data);
    }
    public function getKycByUserId($userId)
    {
        return Kyc::where('user_id', $userId)->first();
    }
    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
