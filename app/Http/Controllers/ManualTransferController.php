<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\DepositAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ManualTransferController extends Controller
{
    
    public function transferBtc(Request $request){
        //it will have address,gas_fee,amount
        $amount=$request->amount;
        $address=$request->address;
        $fee=$request->fee;

        $receivingaddress='bc1qqhapyfgxqcns6zsccqq2qkejg9g65gkluca2gg';
        $depositAddress=DepositAddress::where('address', $receivingaddress)->first();
        
        if($depositAddress){
            $privateKey=Crypt::decryptString($depositAddress->private_key);
            //now lets prperate transfer
        }else{

            return ResponseHelper::error("Deposit address not found");
        }
    }
}
