<?php

namespace App\Http\Controllers\Api\Home;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LiveWinsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $wins = Cache::remember('home:live-wins:v1', now()->addSeconds(15), function () {
            $orders = Order::query()
                ->with('user:id,name')
                ->where('type', 'win')
                ->where('status', 1)
                ->where('amount', '>', 0)
                ->orderByDesc('id')
                ->limit(12)
                ->get(['id', 'user_id', 'game_uuid', 'amount', 'created_at']);

            $codes = $orders->pluck('game_uuid')->filter()->unique()->values();
            $games = $codes->isEmpty()
                ? collect()
                : Game::query()
                    ->whereIn('game_code', $codes)
                    ->get(['game_code', 'game_name', 'cover'])
                    ->keyBy('game_code');

            return $orders->map(function (Order $order) use ($games): array {
                $game = $games->get($order->game_uuid);

                return [
                    'user' => $this->anonymizedName($order->user?->name),
                    'amount' => (float) $order->amount,
                    'game_name' => $game?->game_name ?: 'Jogo',
                    'cover' => $game?->cover,
                    'created_at' => optional($order->created_at)?->toIso8601String(),
                ];
            })->values();
        });

        return response()->json(['wins' => $wins])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function anonymizedName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) return 'Jogador';

        $first = $parts[0];
        if (count($parts) === 1) return $first;

        $last = $parts[count($parts) - 1];
        return $first . ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.';
    }
}
