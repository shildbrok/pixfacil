<?php



namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfigRoundsFree;
use Illuminate\Http\JsonResponse;

class RoundsFreeConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = ConfigRoundsFree::with('game:id,game_code,game_name')
            ->active()
            ->orderBy('value', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'amount' => (float) $item->value,
                    'spins' => (int) $item->spins,
                    'game_code' => $item->game_code,
                    'game_name' => $item->game?->game_name ?: $item->game_code,
                    'status' => (bool) $item->status,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }
}