<?php



namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyBonusConfig;
use App\Models\DailyBonusClaim;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyBonusController extends Controller
{

    public function check(Request $request)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['error' => 'Usuário não autenticado.'], 401);
        }

        $config = DailyBonusConfig::first();
        if (! $config) {
            return response()->json(['error' => 'Config de Bônus não encontrada.'], 500);
        }

        $lastClaim = DailyBonusClaim::where('user_id', $user->id)
            ->orderByDesc('claimed_at')
            ->first();


        if (! $lastClaim) {
            return response()->json([
                'can_claim'       => true,
                'remaining_hours' => 0,
                'message'         => 'Você pode resgatar agora (primeiro resgate).',
                'bonus_value'     => $config->bonus_value,
            ]);
        }

        $nextAllowed = $lastClaim->claimed_at->copy()->addHours((int) $config->cycle_hours);

        if (! $nextAllowed->isFuture()) {
            return response()->json([
                'can_claim'       => true,
                'remaining_hours' => 0,
                'message'         => 'Você já pode resgatar o bônus novamente.',
                'bonus_value'     => $config->bonus_value,
            ]);
        }


        $remaining = (int) ceil(now()->diffInMinutes($nextAllowed) / 60);

        return response()->json([
            'can_claim'       => false,
            'remaining_hours' => $remaining,
            'message'         => "Faltam aproximadamente {$remaining} hora(s) para novo resgate.",
            'bonus_value'     => $config->bonus_value,
        ]);
    }


    public function claim(Request $request)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['error' => 'Usuário não autenticado.'], 401);
        }

        $config = DailyBonusConfig::first();
        if (! $config) {
            return response()->json(['error' => 'Config de Bônus não encontrada.'], 500);
        }

        $payload = DB::transaction(function () use ($user, $config) {





            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                return ['code' => 404, 'error' => 'Carteira não encontrada.'];
            }



            $lastClaim = DailyBonusClaim::where('user_id', $user->id)
                ->lockForUpdate()
                ->orderByDesc('claimed_at')
                ->first();

            if ($lastClaim) {
                $nextAllowed = $lastClaim->claimed_at->copy()->addHours((int) $config->cycle_hours);

                if ($nextAllowed->isFuture()) {
                    $remaining = (int) ceil(now()->diffInMinutes($nextAllowed) / 60);
                    return [
                        'code'  => 422,
                        'error' => "Aguarde {$remaining} hora(s) para resgatar novamente.",
                    ];
                }
            }

            $wallet->balance_bonus          += (float) $config->bonus_value;
            $wallet->balance_bonus_rollover += (float) $config->bonus_value * 2;
            $wallet->save();

            DailyBonusClaim::create([
                'user_id'    => $user->id,
                'claimed_at' => now(), 
            ]);

            return [
                'success' => true,
                'message' => "Bônus de R$ {$config->bonus_value} resgatado com sucesso!",
                'wallet'  => [
                    'balance_bonus'          => (float) $wallet->balance_bonus,
                    'balance_bonus_rollover' => (float) $wallet->balance_bonus_rollover,
                ],
            ];
        });

        if (isset($payload['code'])) {
            return response()->json(['error' => $payload['error']], $payload['code']);
        }

        return response()->json($payload);
    }
}