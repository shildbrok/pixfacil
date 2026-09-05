<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Order;

class RecentsController extends Controller
{

    public function index()
    {

        $gamesPlayed = Order::where('user_id', auth('api')->id())
            ->groupBy('game_uuid')
            ->pluck('game_uuid');

        $games = Game::whereIn('game_code', $gamesPlayed)->get();
        if(count($games) > 0) {
            return response()->json(['games' => $games]);
        }

        // M-05: 'sem jogos recentes' nao e erro — retorna 200 com lista vazia.
        return response()->json(['games' => []], 200);
    }

    

    

    

    

    

    
}
