<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReceivedAsset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function getAvaialbleAsset()
    {
        $assets = ReceivedAsset::where('status', 'inWallet')->get();
        return $assets;
    }
}
