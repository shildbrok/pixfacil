<?php



namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PlayFiverBalanceService;

class GameOpenController extends Controller
{
    public function __construct(private readonly PlayFiverBalanceService $aggregatorService)
    {
    }

    /**
     * O front chama isto antes de abrir um jogo. A regra de "precisa ter depósito
     * pago hoje" foi removida; o que resta é o gate do agregador (saldo/estado da
     * conta PlayFiver), que é outra coisa e continua valendo.
     */
    public function checkDailyDeposit(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Usuário não autenticado.'], 401);
        }

        $gate = $this->aggregatorService->gateForAction('game');
        if (! ($gate['allowed'] ?? false)) {
            return response()->json([
                'can_open' => false,
                'blocked' => true,
                'message' => $gate['message'],
                'details' => $gate['details'] ?? null,
            ], $gate['status_code'] ?? 423);
        }

        return response()->json([
            'can_open' => true,
        ]);
    }
}
