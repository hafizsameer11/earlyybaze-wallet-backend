<?php

namespace App\Repositories;

use App\Models\Kyc;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class KycRepository
{
    public function getKycSummary()
    {
        $totalUsers = User::count();
        $verifiedUsers = User::whereHas('kyc', function ($query) {
            $query->where('status', 'approved');
        })->count();
        $unverifiedUsers = $totalUsers - $verifiedUsers;

        // Fetch all KYC records with user data
        $kycData = Kyc::with('user')->orderBy('id', 'desc')->get();

        return [
            'summary' => [
                [
                    'icon' => 'kycIcon',
                    'iconBg' => 'bg-[#2B12B9]',
                    'heading' => 'total',
                    'subheading' => 'users',
                    'cardValue' => number_format($totalUsers),
                    'valueStatus' => false,
                ],
                [
                    'icon' => 'kycIcon',
                    'iconBg' => 'bg-[#2B12B9]',
                    'heading' => 'total',
                    'subheading' => 'verified users',
                    'cardValue' => number_format($verifiedUsers),
                    'valueStatus' => false,
                ],
                [
                    'icon' => 'kycIcon',
                    'iconBg' => 'bg-[#2B12B9]',
                    'heading' => 'total',
                    'subheading' => 'unverified users',
                    'cardValue' => number_format($unverifiedUsers),
                    'valueStatus' => false,
                ],
            ],
            'kyc_data' => $kycData,
        ];
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
        $kyc = Kyc::find($id);
        if (!$kyc) {
            throw new \Exception('KYC not found');
        }
        $status = $data['status'] ?? null;
        if ($status) {
            //check if status is rejected than add rejection_reason
            if ($status == 'rejected') {
                $data['rejection_reason'] = $data['rejection_reason'] ?? null;
            }
            $kyc->update(['status' => $status]);
        }
        // $kyc->update($data);
        return $kyc;
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
