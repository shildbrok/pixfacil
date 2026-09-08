<?php



namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionUser;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MissionController extends Controller
{

    public function index()
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $today = Carbon::today()->toDateString();

        $missions = $this->activeMissions();

        if ($missions->isEmpty()) {
            return response()->json([]);
        }

        $agg = $this->dailyAggregates($user->id, $today);

        $result = $missions->map(function (Mission $mission) use ($user, $agg) {
            $currentProgress = $this->progressForMission($user->id, $mission, $agg);

            $target = (float) $mission->target_amount;
            $progressPct = $target > 0
                ? min(($currentProgress / $target) * 100.0, 100.0)
                : 0.0;

            return [
                'id'            => $mission->id,
                'title'         => $mission->title,
                'description'   => $mission->description,
                'type'          => $mission->type,
                'target_amount' => (float) $mission->target_amount,
                'reward'        => (float) $mission->reward,
                'image'         => $mission->image,
                'updated_at'    => $mission->updated_at,
                'progress'      => round($progressPct, 2),
                'completed'     => $progressPct >= 100.0,
            ];
        });

        return response()->json($result)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }


    public function updateProgress($missionId)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $mission = Mission::select(['id', 'type', 'game_id', 'target_amount', 'reward', 'status'])
            ->findOrFail($missionId);

        $today = Carbon::today()->toDateString();
        $agg   = $this->dailyAggregates($user->id, $today);

        $currentProgress = $this->progressForMission($user->id, $mission, $agg);
        $target = (float) $mission->target_amount;
        $progressPct = $target > 0 ? min(($currentProgress / $target) * 100.0, 100.0) : 0.0;

        return response()->json([
            'success'  => true,
            'message'  => 'Progresso atualizado.',
            'progress' => [
                'mission_id'       => $mission->id,
                'current_progress' => round($currentProgress, 2),
                'target_amount'    => $target,
                'percent'          => round($progressPct, 2),
                'completed'        => $progressPct >= 100.0,
            ],
        ]);
    }


    public function checkIfRedeemed($missionId)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $redeemed = MissionUser::query()
            ->where('user_id', $user->id)
            ->where('mission_id', $missionId)
            ->where('progress_date', Carbon::today()->toDateString())
            ->where('redeemed', 1)
            ->exists();

        return response()->json(['redeemed' => $redeemed]);
    }


    public function redeemReward($missionId)
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $mission = Mission::select(['id', 'type', 'game_id', 'target_amount', 'reward', 'status'])
            ->find($missionId);

        if (! $mission || $mission->status !== 'active') {
            return response()->json(['message' => 'Missão não encontrada.'], 404);
        }

        $today = Carbon::today()->toDateString();


        $agg      = $this->dailyAggregates($user->id, $today);
        $progress = $this->progressForMission($user->id, $mission, $agg);

        if ($progress + 1e-6 < (float) $mission->target_amount) {
            return response()->json(['message' => 'Você ainda não completou esta missão.'], 403);
        }

        $alreadyRedeemed = false;

        DB::transaction(function () use ($user, $mission, $today, &$alreadyRedeemed) {

            $mu = MissionUser::query()
                ->where('user_id', $user->id)
                ->where('mission_id', $mission->id)
                ->where('progress_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $mu) {
                $mu = new MissionUser([
                    'user_id'       => $user->id,
                    'mission_id'    => $mission->id,
                    'progress_date' => $today,
                ]);
            }

            if ((int) $mu->redeemed === 1) {
                $alreadyRedeemed = true;
                return;
            }


            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->select(['id', 'user_id', 'balance_bonus', 'balance_bonus_rollover'])
                ->firstOrFail();

            $mu->current_progress = (float) $mission->target_amount; 
            $mu->reward           = (float) $mission->reward;
            $mu->redeemed         = 1;
            $mu->save();

            $wallet->balance_bonus          = (float) $wallet->balance_bonus + (float) $mission->reward;
            $wallet->balance_bonus_rollover = (float) $wallet->balance_bonus_rollover + (float) $mission->reward;
            $wallet->save();
        });

        if ($alreadyRedeemed) {
            return response()->json(['message' => 'Missão já resgatada hoje.'], 409);
        }


        Cache::forget("missions:v1:agg:u:{$user->id}:{$today}");

        $walletFresh = Wallet::query()
            ->where('user_id', $user->id)
            ->select(['balance_bonus', 'balance_bonus_rollover'])
            ->first();

        return response()->json([
            'message' => 'Recompensa resgatada com sucesso!',
            'wallet'  => [
                'balance_bonus'          => (float) ($walletFresh->balance_bonus ?? 0),
                'balance_bonus_rollover' => (float) ($walletFresh->balance_bonus_rollover ?? 0),
            ],
        ]);
    }




    private function activeMissions()
    {


        $fp = DB::table('missions')->where('status', 'active')
            ->selectRaw('COUNT(*) as c, MAX(updated_at) as m')->first();
        $cacheKey = 'missions:v2:active:list:' . ($fp->c ?? 0) . ':' . ($fp->m ?? 'none');

        return Cache::remember($cacheKey, now()->addDay(), function () {
            return Mission::query()
                ->where('status', 'active')
                ->select([
                    'id', 'title', 'description', 'type',
                    'target_amount', 'reward', 'image', 'game_id', 'status', 'updated_at',
                ])
                ->orderBy('id')
                ->get();
        });
    }


    private function dailyAggregates(int $userId, string $today): array
    {
        $aggKey = "missions:v1:agg:u:{$userId}:{$today}";

        return Cache::remember($aggKey, 60, function () use ($userId, $today) {
            $start = Carbon::parse($today)->startOfDay();
            $end   = Carbon::parse($today)->endOfDay();

            $ordersByGame = Order::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->select([
                    'game',
                    DB::raw("SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END) AS sum_bet"),
                    DB::raw("SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) AS sum_win"),
                    DB::raw("SUM(CASE WHEN type = 'bet' THEN 1 ELSE 0 END) AS rounds_cnt"),
                ])
                ->groupBy('game')
                ->get();

            $sumBetByGame = [];
            $sumWinByGame = [];
            $roundsByGame = [];

            foreach ($ordersByGame as $row) {
                $g = (string) $row->game;
                $sumBetByGame[$g] = (float) $row->sum_bet;
                $sumWinByGame[$g] = (float) $row->sum_win;
                $roundsByGame[$g] = (int) $row->rounds_cnt;
            }

            $sumTotalBet = (float) Order::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->where('type', 'bet')
                ->sum('amount');

            $sumDeposits = (float) Transaction::query()
                ->where('user_id', $userId)
                ->where('status', 1) // A-01: só depósitos APROVADOS contam (antes um PIX pendente/nao-pago completava a missao)
                ->whereBetween('created_at', [$start, $end])
                ->sum('price');

            return [
                'sumBetByGame'   => $sumBetByGame,
                'sumWinByGame'   => $sumWinByGame,
                'roundsByGame'   => $roundsByGame,
                'sumTotalBet'    => $sumTotalBet,
                'sumDeposits'    => $sumDeposits,
            ];
        });
    }


    private function progressForMission(int $userId, Mission $mission, array $agg): float
    {
        $gameId = (string) ($mission->game_id ?? '');

        return match ($mission->type) {
            'game_bet'      => (float) ($agg['sumBetByGame'][$gameId] ?? 0.0),
            'total_bet'     => (float) ($agg['sumTotalBet'] ?? 0.0),
            'deposit'       => (float) ($agg['sumDeposits'] ?? 0.0),
            'rounds_played' => (float) ($agg['roundsByGame'][$gameId] ?? 0),
            'win_amount'    => (float) ($agg['sumWinByGame'][$gameId] ?? 0.0),
            'loss_amount'   => max(0.0, ($agg['sumBetByGame'][$gameId] ?? 0.0) - ($agg['sumWinByGame'][$gameId] ?? 0.0)),
            default         => 0.0,
        };
    }
}