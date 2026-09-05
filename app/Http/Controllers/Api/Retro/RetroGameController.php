<?php

namespace App\Http\Controllers\Api\Retro;

use App\Http\Controllers\Controller;
use App\Models\HouseGame;
use App\Models\HouseGameRound;
use App\Models\Wallet;
use App\Services\HouseGames\HouseGameWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RetroGameController extends Controller
{
    public function __construct(private readonly HouseGameWalletService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = HouseGame::query()->where('active', true)->orderBy('sort_order')->orderBy('name');
        if ($request->boolean('home')) {
            $query->where('show_home', true);
        }

        return response()->json([
            'status' => true,
            'games' => $query->get()->map(fn (HouseGame $game): array => $this->gamePayload($game))->values(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $game = HouseGame::query()->where('slug', $slug)->where('active', true)->first();
        if (! $game) {
            return response()->json(['message' => 'Jogo retrô não encontrado.'], 404);
        }

        return response()->json(['status' => true, 'game' => $this->gamePayload($game)]);
    }

    public function start(Request $request, string $slug): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $game = HouseGame::query()->where('slug', $slug)->where('active', true)->first();
        if (! $game) {
            return response()->json(['message' => 'Jogo retrô não encontrado.'], 404);
        }

        $validated = $request->validate([
            'bet' => ['required', 'numeric'],
            'client_event_id' => ['required', 'uuid'],
        ]);

        try {
            $result = $this->service->start($game, $user, (float) $validated['bet'], (string) $validated['client_event_id']);
            $round = $result['round'];
            $wallet = Wallet::query()->where('user_id', $user->id)->where('active', 1)->first()
                ?: Wallet::query()->where('user_id', $user->id)->first();

            return $this->roundResponse($request, $game, $round, $result['engine_token'], [
                'status' => true,
                'reused' => (bool) ($result['reused'] ?? false),
                'balance' => (float) ($wallet?->total_balance ?? 0),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Retro game start failed', ['game' => $slug, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Não foi possível iniciar a rodada.'], 500);
        }
    }

    public function launch(Request $request, string $slug): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $game = HouseGame::query()->where('slug', $slug)->where('active', true)->first();
        if (! $game) {
            return response()->json(['message' => 'Jogo retrô não encontrado.'], 404);
        }

        try {
            $result = $this->service->launch($game, $user);
            $game->increment('views');

            return $this->roundResponse($request, $game, $result['round'], $result['engine_token'], ['status' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function forfeit(Request $request, string $slug): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $game = HouseGame::query()->where('slug', $slug)->first();
        if (! $game) {
            return response()->json(['message' => 'Jogo retrô não encontrado.'], 404);
        }

        $this->service->forfeit($game, $user);

        $response = response()->json(['status' => true]);
        return $this->clearRoundCookie($response, $request, $game);
    }

    public function lastResult(string $slug): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $game = HouseGame::query()->where('slug', $slug)->first();
        if (! $game) {
            return response()->json(['message' => 'Jogo retrô não encontrado.'], 404);
        }

        $round = HouseGameRound::query()
            ->where('user_id', $user->id)
            ->where('house_game_id', $game->id)
            ->orderByDesc('id')
            ->first();

        if (! $round) {
            return response()->json(['status' => true, 'round' => null]);
        }

        return response()->json([
            'status' => true,
            'round' => [
                'id' => $round->round_uuid,
                'status' => $round->status,
                'bet' => (float) $round->bet,
                'payout' => (float) $round->payout,
                'settled_at' => optional($round->settled_at)->toIso8601String(),
            ],
        ]);
    }

    private function gamePayload(HouseGame $game): array
    {
        return [
            'id' => $game->id,
            'slug' => $game->slug,
            'name' => $game->name,
            'description' => $game->description,
            'cover' => $game->cover,
            'cover_url' => $this->assetUrl($game->cover),
            'icon_url' => $this->assetUrl($game->icon),
            'min_bet' => (float) $game->min_bet,
            'max_bet' => (float) $game->max_bet,
            'meta_multiplier' => (float) $game->meta_multiplier,
            'max_win_multiplier' => (float) $game->max_win_multiplier,
            'player_speed' => (float) $game->player_speed,
            'show_home' => (bool) $game->show_home,
            'views' => (int) $game->views,
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'retro-games/') || str_starts_with($path, 'pixfacil-')) {
            return asset($path);
        }
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }
        if (str_starts_with($path, 'uploads/')) {
            return asset('storage/' . $path);
        }
        return asset('storage/' . $path);
    }

    private function roundResponse(Request $request, HouseGame $game, HouseGameRound $round, string $token, array $extra): JsonResponse
    {
        $response = response()->json(array_merge($extra, [
            'game' => $this->gamePayload($game),
            'round' => [
                'id' => $round->round_uuid,
                'bet' => (float) $round->bet,
                'meta' => (float) $round->meta_amount,
                'max_payout' => (float) $round->max_payout,
                'status' => $round->status,
                'expires_at' => optional($round->expires_at)->toIso8601String(),
            ],
        ]));

        $minutes = max(2, (int) ceil(max(60, (int) $game->round_timeout_seconds) / 60));
        $response->cookie(
            HouseGameWalletService::cookieName($game->slug),
            $token,
            $minutes,
            '/api/retro/engine/' . $game->slug,
            null,
            $request->isSecure(),
            true,
            false,
            'Strict'
        );

        return $response;
    }

    private function clearRoundCookie(JsonResponse $response, Request $request, HouseGame $game): JsonResponse
    {
        $response->cookie(
            HouseGameWalletService::cookieName($game->slug),
            '',
            -1,
            '/api/retro/engine/' . $game->slug,
            null,
            $request->isSecure(),
            true,
            false,
            'Strict'
        );
        return $response;
    }
}
