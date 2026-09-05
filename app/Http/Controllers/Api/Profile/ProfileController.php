<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'Não autenticado',
            ], 401);
        }

        $userId = $user->id;


        $stats = Order::query()
            ->where('user_id', $userId)
            ->selectRaw("
                SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END)  AS total_earnings,
                SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END)  AS sum_bets,
                SUM(CASE WHEN type = 'bet' THEN 1      ELSE 0 END)  AS total_bets
            ")
            ->first();

        $totalEarnings = (float) ($stats->total_earnings ?? 0);
        $sumBets       = (float) ($stats->sum_bets ?? 0);
        $totalBets     = (int)   ($stats->total_bets ?? 0);

        return response()->json([
            'status'          => true,
            'user'            => $user,
            'totalEarnings'   => \Helper::amountFormatDecimal($totalEarnings),
            'totalBets'       => $totalBets,
            'sumBets'         => \Helper::amountFormatDecimal($sumBets),
            'defaultLanguage' => 'pt-BR',
        ]);
    }


    public function getLanguage(Request $request): JsonResponse
    {

        if (auth('api')->check()) {
            $user = auth('api')->user();

            if (! empty($user->language)) {
                return response()->json([
                    'language' => $user->language,
                ]);
            }
        }


        return response()->json([
            'language' => 'pt-BR',
        ]);
    }


    public function selectAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar' => 'required|string|max:64',
        ]);

        $allowed = [];
        for ($i = 1; $i <= 9; $i++) {
            $allowed[] = "avatars/av-{$i}.svg";
        }

        if (! in_array($data['avatar'], $allowed, true)) {
            return response()->json(['status' => false, 'message' => 'Avatar inválido.'], 422);
        }

        $user = auth('api')->user();
        $user->avatar = $data['avatar'];
        $user->save();

        return response()->json([
            'status' => true,
            'avatar' => $user->avatar,
            'user'   => $user->fresh(),
        ]);
    }
}
