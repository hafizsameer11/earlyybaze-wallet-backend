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
        return InAppBanner::create($data); // Create a new banner
    }

    public function update($id, array $data)
    {
        $banner = InAppBanner::findOrFail($id); // Find banner
        $banner->update($data); // Update banner
        return $banner;
    }

    public function delete($id)
    {
        $banner = InAppBanner::findOrFail($id); // Find banner
        return $banner->delete(); // Delete banner
    }
}
