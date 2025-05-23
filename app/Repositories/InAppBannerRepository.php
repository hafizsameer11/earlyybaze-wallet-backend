<?php

namespace App\Repositories;

use App\Models\InAppBanner;

class InAppBannerRepository
{
    public function all()
    {
        return InAppBanner::all(); // Fetch all banners
    }

    public function find($id)
    {
        return InAppBanner::findOrFail($id); // Fetch banner by ID
    }

    public function create(array $data)
    {
        if (isset($data["attachment"]) && $data["attachment"]) {
            $path = $data['attachment']->store('banners', 'public');
            $data['attachment'] = $path;
        }
        return InAppBanner::create($data); // Create a new banner
    }

    public function update($id, array $data)
    {
        $banner = InAppBanner::findOrFail($id); // Find banner
        if (isset($data["attachment"]) && $data["attachment"]) {
            $path = $data['attachment']->store('banners', 'public');
            $data['attachment'] = $path;
        }
        $banner->update($data); // Update banner
        return $banner;
    }

    public function delete($id)
    {
        $banner = InAppBanner::findOrFail($id); // Find banner
        return $banner->delete(); // Delete banner
    }
}
