<?php

namespace App\Http\Controllers\Api\Retro;

use App\Http\Controllers\Controller;
use App\Models\HouseGame;
use App\Services\HouseGames\HouseGameWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class RetroEngineController extends Controller
{
    public function __construct(private readonly HouseGameWalletService $service)
    {
    }

    public function info(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->denyCrossSite($request)) {
            return $denied;
        }

        [$game, $round, $error] = $this->resolveRound($request, $slug);
        if ($error) {
            return $error;
        }

        try {
            return response()->json($this->service->engineInfo($game, $round));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'play_url' => url('/retro/game/' . $slug)], 409);
        }
    }

    public function win(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->denyCrossSite($request)) {
            return $denied;
        }

        [$game, $round, $error] = $this->resolveRound($request, $slug);
        if ($error) {
            return $error;
        }

        try {
            // O navegador apenas sinaliza que a mecânica local chegou ao ponto de
            // liquidação. Resultado e valor financeiro são decididos no servidor.
            $result = $this->service->settleWin($game, $round);
            return response()->json([
                'ok' => true,
                'outcome' => (string) $result['outcome'],
                'payout' => (float) $result['payout'],
                'balance' => (float) $result['balance'],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível liquidar o prêmio.'], 500);
        }
    }

    public function lost(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->denyCrossSite($request)) {
            return $denied;
        }

        [$game, $round, $error] = $this->resolveRound($request, $slug);
        if ($error) {
            // Perda repetida é idempotente para o engine.
            if ($error->getStatusCode() === 409) {
                return response()->json(['ok' => true]);
            }
            return $error;
        }

        try {
            $result = $this->service->settleLoss($game, $round);
            return response()->json(['ok' => true, 'balance' => (float) $result['balance']]);
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível encerrar a rodada.'], 500);
        }
    }

    private function resolveRound(Request $request, string $slug): array
    {
        $game = HouseGame::query()->where('slug', $slug)->where('active', true)->first();
        if (! $game) {
            return [null, null, response()->json(['message' => 'Jogo retrô não encontrado.'], 404)];
        }

        $token = $request->cookie(HouseGameWalletService::cookieName($slug));
        $round = $this->service->roundFromEngineToken($game, $token);
        if (! $round) {
            return [$game, null, response()->json([
                'message' => 'Nenhuma rodada autorizada para este jogo.',
                'play_url' => url('/retro/game/' . $slug),
            ], 409)];
        }

        return [$game, $round, null];
    }

    private function denyCrossSite(Request $request): ?JsonResponse
    {
        $site = strtolower((string) $request->header('Sec-Fetch-Site'));
        if ($site === 'cross-site') {
            return response()->json(['message' => 'Origem inválida.'], 403);
        }

        $origin = $request->header('Origin');
        if ($origin) {
            $host = parse_url($origin, PHP_URL_HOST);
            if ($host && strcasecmp($host, $request->getHost()) !== 0) {
                return response()->json(['message' => 'Origem inválida.'], 403);
            }
        }

        return null;
    }
}
