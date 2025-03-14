<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\RefferalEarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefferalEarningController extends Controller
{
    protected $refferalEarningService;
    public function __construct(RefferalEarningService $refferalEarningService)
    {
        $this->refferalEarningService = $refferalEarningService;
    }
    public function getForAuthUser(){
        try{
            $user = Auth::user();
        $data=$this->refferalEarningService->getByUserId($user->id);
        return ResponseHelper::success($data,"Data fetched successfully",200);

        }catch(\Exception $e){
            return ResponseHelper::error($e->getMessage());
        }
    }
}
