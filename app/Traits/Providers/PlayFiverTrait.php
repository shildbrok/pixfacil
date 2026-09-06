<?php



namespace App\Traits\Providers;

use App\Models\GamesKey;
use App\Models\Order;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as FacadesRequest;
use App\Helpers\Core as Helper;
use App\Services\Wallets\WalletRolloverService;
use App\Support\PlayFiverLog;
use RuntimeException;
use Throwable;

trait PlayFiverTrait
{


    protected static $secretPlayFiver;
    protected static $codePlayFiver;
    protected static $tokenPlayFiver;

    private static function credencialFiverPlay()
    {
        $setting = GamesKey::first();

        self::$secretPlayFiver = $setting?->playfiver_secret;
        self::$codePlayFiver   = $setting?->playfiver_code;
        self::$tokenPlayFiver  = $setting?->playfiver_token;
    }

    private static function sanitizeWebhookContext(array $data): array
    {
        $redactKeys = [
            'agent_secret',
            'secretKey',
            'secret_key',
            'agentToken',
            'agent_token',
            'token',
            'access_token',
        ];

        foreach ($redactKeys as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = '[REDACTED]';
            }
        }

        return $data;
    }

    public static function playFiverLaunch($id, $demo)
    {
        self::credencialFiverPlay();

	$game = Game::where("game_code", $id)->first();

        $postArray = [
            "agentToken" => self::$tokenPlayFiver,
            "secretKey" => self::$secretPlayFiver,
            "user_code" => Auth::guard("api")->user()->email,
            "game_code" => $id,
            "game_original" => $game?->original == 1 ? true : false,
            "user_balance" => self::getBalancePlayFiver(Auth::guard("api")->user()->id),
        ];

        try {
            $response = Http::withOptions([
                'force_ip_resolve' => 'v4',
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])->post("https://api.playfivers.com/api/v2/game_launch", $postArray);
        } catch (Throwable $e) {
            PlayFiverLog::exception('falha de conexão ao lançar jogo', $e, [
                'game_code' => $id,
                'game_original' => $postArray['game_original'],
                'user_id' => Auth::guard("api")->id(),
            ]);

            return ["msg" => "Erro ao lançar o jogo"];
        }

        if ($response->successful()) {
            $data = $response->json();

            if (! filled($data['launch_url'] ?? null)) {
                PlayFiverLog::warning('resposta sem URL de lançamento', $response->status(), $data, [
                    'game_code' => $id,
                    'game_original' => $postArray['game_original'],
                    'user_id' => Auth::guard("api")->id(),
                ]);

                return ["msg" => "Erro ao lançar o jogo"];
            }

            return ["launch_url" => $data['launch_url']];
        }

        PlayFiverLog::warning('falha ao lançar jogo', $response->status(), $response->json() ?: $response->body(), [
            'game_code' => $id,
            'game_original' => $postArray['game_original'],
            'user_id' => Auth::guard("api")->id(),
            'user_balance' => $postArray['user_balance'],
        ]);

        return ["msg" => "Erro ao lançar o jogo"];
    }
    public static function webhookPlayFiverAPI(Request $request)
    {

        self::credencialFiverPlay();

        $agentSecret = $request->input('agent_secret');
        $agentCode   = $request->input('agent_code');

        $okSecret = is_string($agentSecret) && is_string(self::$secretPlayFiver)
            ? hash_equals(self::$secretPlayFiver, $agentSecret)
            : false;
        $okCode = is_string($agentCode) && is_string(self::$codePlayFiver)
            ? hash_equals(self::$codePlayFiver, $agentCode)
            : false;

        if (!$okSecret || !$okCode) {
            PlayFiverLog::warning('webhook recusado', 401, ['message' => 'agent_secret ou agent_code inválido'], [
                'ip' => request()->ip(),
                'agent_code' => $agentCode,
                'dados' => self::sanitizeWebhookContext($request->all()),
            ]);
            return response()->json(["msg" => "UNAUTHORIZED", "balance" => 0], 401);
        }

        $tipo = $request->input("type");
        switch ($tipo) {
            case 'Balance':
                return self::getBalancePlayFiverAPI($request);
            case 'WinBet':
                return self::percaOuGanhoPlayFiver($request);
            case 'Refund':
                return self::refundPlayFiverApi($request);
            default:


                return response()->json(["msg" => "UNKNOWN_TYPE", "balance" => 0], 200);
        }
    }
    private static function getBalancePlayFiverAPI($dados)
    {
        $user = User::where("email", $dados->input("user_code"))->first();
        if ($user != null && $user->wallet != null) {
            $w = $user->wallet;
            $saldo = (float)$w->balance + (float)$w->balance_bonus + (float)$w->balance_withdrawal;

            return response()->json(["msg" => "", "balance" => number_format($saldo, 2, ".", "")]);
        }

        return response()->json(["msg" => "INVALID_USER", "balance" => 0]);
    }
    private static function refundPlayFiverApi($dados)
    {
        $user = User::where("email", $dados->input("user_code"))->first();
        $detalhes = $dados->input($dados->input("game_type"));


        if ($user == null || !is_array($detalhes) || !isset($detalhes["round_id"])) {
            return response()->json(["msg" => "INVALID_USER", "balance" => 0]);
        }

        // Escopado ao usuário do webhook: sem o user_id, um round_id de outra pessoa
        // seria marcado como refunded e o crédito iria para a carteira errada.
        $order = Order::where("round_id", $detalhes["round_id"])
            ->where("user_id", $user->id)
            ->where('providers', 'play_fiver')
            ->where('type', 'bet')
            ->orderByDesc('id')
            ->first();
        if ($order == null) {
            return response()->json(["msg" => "INVALID_USER", "balance" => 0]);
        }

        $saldo = \DB::transaction(function () use ($order, $user) {
            $order = Order::query()->lockForUpdate()->find($order->id);
            if (! $order || (int) $order->refunded === 1) {
                return (float) $user->wallet->total_balance;
            }

            $order->update(["refunded" => true]);

            $wallet = $user->wallet()->lockForUpdate()->first();
            $refundAmount = max(0, (float) $order->amount);
            $wallet->increment("balance_withdrawal", $refundAmount);

            return (float) $wallet->fresh()->total_balance;
        });

        return response()->json(["msg" => "", "balance" => number_format($saldo, 2, ".", "")]);
    }
    private static function percaOuGanhoPlayFiver($dados)
    {
        self::credencialFiverPlay();


        $agentSecret = $dados->input('agent_secret');
        $agentCode   = $dados->input('agent_code');

        $okSecret = is_string($agentSecret) && is_string(self::$secretPlayFiver)
            ? hash_equals(self::$secretPlayFiver, $agentSecret)
            : false;
        $okCode = is_string($agentCode) && is_string(self::$codePlayFiver)
            ? hash_equals(self::$codePlayFiver, $agentCode)
            : false;

        if (!$okSecret || !$okCode) {
            PlayFiverLog::warning('WinBet recusado', 401, ['message' => 'agent_secret ou agent_code inválido'], [
                'ip' => request()->ip(),
                'agent_code' => $agentCode,
                'dados' => self::sanitizeWebhookContext($dados->all()),
            ]);
            return response()->json(["msg" => "UNAUTHORIZED", "balance" => 0], 401);
        }

        $user = User::where("email", $dados->input("user_code"))->first();
        if ($user == null) {
            PlayFiverLog::warning('usuário não encontrado no webhook', 500, ['message' => 'user_code não encontrado na base local'], [
                'user_code' => $dados->input('user_code'),
                'ip' => request()->ip(),
            ]);
            return response()->json(["balance" => 0, "msg" => "INVALID_USER"], 500);
        }

        $detalhes = $dados->input($dados->input("game_type"));
        if (!is_array($detalhes)) {
            PlayFiverLog::warning('payload inválido no webhook', 400, ['message' => 'payload sem bloco esperado do game_type'], [
                'type' => $dados->input('type'),
                'game_type' => $dados->input('game_type'),
                'ip' => request()->ip(),
            ]);
            return response()->json(["balance" => 0, "msg" => "INVALID_PAYLOAD"], 400);
        }

        $txnId   = $detalhes['txn_id'] ?? null;
        $roundId = $detalhes['round_id'] ?? null;


        $uniqueId = $txnId ?: $roundId;
        if (!$uniqueId) {
            PlayFiverLog::warning('webhook sem identificador de transação', 400, ['message' => 'txn_id e round_id ausentes'], [
                'user_code' => $dados->input('user_code'),
                'payload' => self::sanitizeWebhookContext($dados->all()),
            ]);
            return response()->json(["balance" => 0, "msg" => "MISSING_TXN"], 400);
        }

        $bet = max(0, (float) ($detalhes['bet'] ?? 0));
        $win = max(0, (float) ($detalhes['win'] ?? 0));

        try {
            return \DB::transaction(function () use ($user, $detalhes, $uniqueId, $bet, $win) {
                $wallet = $user->wallet()->lockForUpdate()->first();
                if (!$wallet) {
                    return response()->json(["balance" => 0, "msg" => "INVALID_USER"], 500);
                }




                $orderType = $win > 0 ? 'win' : 'bet';
                $already = Order::where('transaction_id', $uniqueId)
                    ->where('providers', 'play_fiver')
                    ->where('type', $orderType)
                    ->lockForUpdate()
                    ->exists();
                if ($already) {
                    $saldoAtual = (float) ($wallet->fresh()->total_balance ?? 0);
                    return response()->json(["msg" => "", "balance" => number_format($saldoAtual, 2, ".", "")]);
                }

                if ($bet <= 0 && $win <= 0) {
                    $saldoAtual = (float) ($wallet->fresh()->total_balance ?? 0);
                    return response()->json(["msg" => "", "balance" => number_format($saldoAtual, 2, ".", "")]);
                }

                $movement = WalletRolloverService::applyGameResult($wallet, $bet, $win);

                Order::create([
                    "user_id"        => $user->id,
                    "session_id"     => $detalhes['round_id'] ?? null,
                    "transaction_id" => $uniqueId,
                    "game"           => $detalhes['game_code'] ?? null,
                    "game_uuid"      => $detalhes['game_code'] ?? null,
                    "type"           => $win > 0 ? "win" : "bet",
                    "type_money"     => $movement['type_money'] ?? 'mixed',
                    "amount"         => $win > 0 ? $win : $bet,
                    "providers"      => "play_fiver",
                    "refunded"       => false,
                    "round_id"       => $detalhes['round_id'] ?? null,
                    "status"         => true,
                ]);


                Helper::generateGameHistory(
                    $user->id,
                    $win > 0 ? "win" : "bet",
                    $win,
                    $bet,
                    $movement['type_money'] ?? 'mixed',
                    $uniqueId
                );

                $saldo = (float) $wallet->fresh()->total_balance;
                return response()->json(["msg" => "", "balance" => number_format($saldo, 2, ".", "")]);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_USER_FUNDS') {
                PlayFiverLog::warning('WinBet recusado por saldo insuficiente do usuário', 500, ['message' => 'Saldo insuficiente na carteira local do usuário'], [
                    'user_id' => $user->id ?? null,
                    'bet' => $bet,
                    'win' => $win,
                ]);

                return response()->json(["balance" => 0, "msg" => "INSUFFICIENT_USER_FUNDS"], 500);
            }

            throw $e;
        }
    }
    private static function getBalancePlayFiver($id)
    {
        $user = User::where("id", $id)->first();
        if ($user != null && $user->wallet != null) {
            $w = $user->wallet;
            return (float)$w->balance + (float)$w->balance_bonus + (float)$w->balance_withdrawal;
        }
        return 0;
    }
}
