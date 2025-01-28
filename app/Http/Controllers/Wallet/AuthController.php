<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Services\TatumService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $tatumService;
    public function __construct(TatumService $tatumService)
    {
        $this->tatumService = $tatumService;
    }
}
