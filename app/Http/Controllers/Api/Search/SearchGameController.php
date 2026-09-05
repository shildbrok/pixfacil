<?php

namespace App\Http\Controllers\Api\Search;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\PlayFiverGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchGameController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Game::query()
                ->with(['provider:id,code,name,distribution'])
                ->select(['id', 'provider_id', 'game_name', 'game_code', 'cover', 'distribution'])
                ->where('status', 1)
                ->whereHas('provider', fn ($q) => $q->where('distribution', PlayFiverGate::expectedDistribution()));

            if ($request->filled('searchTerm') && strlen((string) $request->input('searchTerm')) > 2) {
                $term = (string) $request->input('searchTerm');
                $query->where(function ($q) use ($term) {
                    $q->where('game_name', 'like', "%{$term}%")
                        ->orWhere('game_code', 'like', "%{$term}%");
                });
            }

            $games = $query->orderByDesc('views')->paginate(12);

            // só os campos de exibição (mantém a paginação)
            $games->through(fn ($g) => [
                'id'           => $g->id,
                'game_name'    => $g->game_name,
                'game_code'    => $g->game_code,
                'cover'        => $g->cover,
                'distribution' => $g->distribution,
                'provider'     => optional($g->provider)->name,
            ]);

            return response()->json(['games' => $games]);
        } catch (\Throwable $e) {
            Log::error('Error fetching games: '.$e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }
}
