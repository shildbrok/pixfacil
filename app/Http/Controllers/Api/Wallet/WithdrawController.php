<?php



namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;

class WithdrawController extends Controller
{

    public function index()
    {
        $withdraws = Withdrawal::whereUserId(auth('api')->id())->paginate();
        return response()->json(['withdraws' => $withdraws], 200);
    }

    

    

    

    

    

    
}
