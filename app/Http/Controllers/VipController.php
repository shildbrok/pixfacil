<?php



namespace App\Http\Controllers;

use App\Models\Vip;
use App\Models\MissionUser;
use App\Models\UserVip;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class VipController extends Controller
{

    public function getVipsWithProgress()
    {
        $user = auth('api')->user();
    
        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }
    

        $vips = Vip::orderBy('required_missions', 'asc')->get();
    

        $completedMissions = MissionUser::where('user_id', $user->id)
            ->where('redeemed', 1)
            ->count();
    

        $userVipRecords = UserVip::where('user_id', $user->id)->pluck('last_reward_claimed_at', 'vip_id');
    

        $currentVip = $vips->filter(function ($vip) use ($completedMissions) {
            return $completedMissions >= $vip->required_missions;
        })->last();
    

        $result = $vips->map(function ($vip) use ($completedMissions, $userVipRecords, $currentVip) {
            $progress = $vip->required_missions > 0 
                ? min(($completedMissions / $vip->required_missions) * 100, 100)
                : 100;
    
            $eligible = $completedMissions >= $vip->required_missions;
            $lastClaimed = $userVipRecords->get($vip->id);
    

            $canClaim = true;
    
            if ($currentVip && $currentVip->id === $vip->id) {
                // Ordem dos operandos importa: no Carbon 3 o diff vem COM SINAL.
                // now()->diffInDays($passado) devolve NEGATIVO, e o >= 7 nunca
                // seria verdade — o jogador nunca mais resgataria. Assim lê
                // "dias desde o resgate" e dá positivo. Data futura (relógio
                // torto) dá negativo e bloqueia, que é o lado seguro do erro.
                $canClaim = !$lastClaimed || $lastClaimed->diffInDays(now()) >= 7;
            } else {

                $canClaim = false;
            }
    

            $img = $vip->image;
            if (! empty($img)) {
                $v = optional($vip->updated_at)->getTimestamp();
                if ($v) {
                    $img .= (str_contains($img, '?') ? '&' : '?') . 'v=' . $v;
                }
            }

            return [
                'id' => $vip->id,
                'title' => $vip->title,
                'description' => $vip->description,
                'required_missions' => $vip->required_missions,
                'weekly_reward' => (float) $vip->weekly_reward,
                'image' => $img,
                'progress' => round($progress, 2),
                'eligible' => $eligible,
                'claimed' => !$canClaim, 
            ];
        });
    
        return response()->json($result)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
    
    public function claimVipReward($vipId)
    {
        $user = auth('api')->user();
    
        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }
    
        $vip = Vip::find($vipId);
    
        if (!$vip) {
            return response()->json(['message' => 'Nível VIP não encontrado.'], 404);
        }
    

        $userVip = UserVip::firstOrCreate(
            ['user_id' => $user->id, 'vip_id' => $vipId],
            ['last_reward_claimed_at' => null]
        );
    

        // Carbon 3: diff com sinal. Ver a nota em canClaim() — invertido, lê
        // "dias desde o resgate" e volta a dar positivo.
        if ($userVip->last_reward_claimed_at && $userVip->last_reward_claimed_at->diffInDays(now()) < 7) {
            return response()->json(['message' => 'Você já resgatou a recompensa semanal deste nível VIP.'], 400);
        }
    

        $completedMissions = MissionUser::where('user_id', $user->id)
            ->where('redeemed', 1)
            ->count();

        if ($completedMissions < $vip->required_missions) {
            return response()->json(['message' => 'Você ainda não alcançou este nível VIP.'], 403);
        }


        $currentVip = Vip::orderBy('required_missions', 'asc')->get()
            ->filter(fn ($v) => $completedMissions >= $v->required_missions)
            ->last();

        if (! $currentVip || (int) $currentVip->id !== (int) $vip->id) {
            return response()->json(['message' => 'Você só pode resgatar a recompensa do seu VIP atual.'], 403);
        }


        $wallet = null;
        try {
            DB::transaction(function () use ($user, $vip, $userVip, &$wallet) {
                $wallet = Wallet::where('user_id', $user->id)
                    ->where('active', 1)
                    ->lockForUpdate()
                    ->firstOrFail();

                $uv = UserVip::query()->lockForUpdate()->findOrFail($userVip->id);
                // Carbon 3: diff com sinal. Esta é a checagem que vale (dentro
                // do lock); a de cima é só para responder cedo.
                if ($uv->last_reward_claimed_at && $uv->last_reward_claimed_at->diffInDays(now()) < 7) {
                    throw new \RuntimeException('Você já resgatou a recompensa semanal deste nível VIP.');
                }

                $wallet->balance_bonus = (float) $wallet->balance_bonus + (float) $vip->weekly_reward;
                $wallet->balance_bonus_rollover = (float) $wallet->balance_bonus_rollover + (float) $vip->weekly_reward;
                $wallet->save();

                $uv->last_reward_claimed_at = now();
                $uv->save();
            });
        } catch (\Throwable $e) {
            report($e);
            $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível concluir a solicitação.';
            return response()->json(['message' => $message], 400);
        }
    
        return response()->json([
            'message' => 'Recompensa semanal resgatada com sucesso!',
            'reward' => $vip->weekly_reward,
            'wallet' => [
                'balance_bonus' => (float) ($wallet?->balance_bonus ?? 0),
                'balance_bonus_rollover' => (float) ($wallet?->balance_bonus_rollover ?? 0),
            ],
        ]);
    }
    
}