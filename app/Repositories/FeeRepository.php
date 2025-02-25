<?php

namespace App\Repositories;

use App\Models\Fee;
use Exception;

class FeeRepository
{
    public function all()
    {
        return Fee::all();
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }

    public function create(array $data)
    {
        return Fee::create($data);
    }

    public function update($id, array $data)
    {
        $fee = Fee::find($id);
        if (!$fee) {
            throw new Exception('Fee not found');
        }
        $fee->update($data);
        return $fee;
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function getByType($type)
    {
        return Fee::where('type', $type)->orderBy('id', 'desc')->first();
    }
}
