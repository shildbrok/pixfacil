<?php



namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Traits\Gateways\GeraPixTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GeraPixController extends Controller
{
    use GeraPixTrait;


    public function callback(Request $request)
    {
        if (config('app.debug')) {
            Log::debug('[GERAPIX] webhook keys', [
                'ip'   => $request->ip(),
                'keys' => array_keys($request->all()),
            ]);
        }

        return $this->webhookGeraPix($request);
    }
}
