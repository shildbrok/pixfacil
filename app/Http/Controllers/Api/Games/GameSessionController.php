<?php



namespace App\Http\Controllers\Api\Games;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameSessionController extends Controller
{

    public function start(Request $request): JsonResponse
    {

        $user = auth('api')->user() ?? auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $validated = $request->validate([
            'game_id'  => 'required|integer',
            'provider' => 'nullable|string|max:255',
        ]);


        GameSession::where('user_id', $user->id)
            ->where('status', GameSession::STATUS_ACTIVE)
            ->update([
                'status'    => GameSession::STATUS_EXPIRED,
                'closed_at' => now(),
            ]);

        $session = GameSession::create([
            'user_id'      => $user->id,
            'game_id'      => $validated['game_id'],
            'provider'     => $validated['provider'] ?? null,
            'status'       => GameSession::STATUS_ACTIVE,
            'started_at'   => now(),
            'last_ping_at' => now(),
            'device'       => $this->detectDevice($request->userAgent()),
            'ip'           => $request->ip(),
        ]);

        return response()->json([
            'session_id' => $session->id,
            'status'     => 'started',
        ]);
    }


    public function ping(Request $request): JsonResponse
    {
        $user = auth('api')->user() ?? auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $validated = $request->validate([
            'session_id' => 'required|integer',
        ]);

        $session = GameSession::where('id', $validated['session_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return response()->json(['message' => 'Sessão não encontrada'], 404);
        }

        if ($session->status !== GameSession::STATUS_ACTIVE) {
            return response()->json(['message' => 'Sessão já encerrada'], 410);
        }


        $maxIdleMinutes = 5;

        if ($session->last_ping_at && $session->last_ping_at->lt(now()->subMinutes($maxIdleMinutes))) {
            $session->update([
                'status'    => GameSession::STATUS_EXPIRED,
                'closed_at' => now(),
            ]);

            return response()->json(['message' => 'Sessão expirada por inatividade'], 410);
        }

        $session->update([
            'last_ping_at' => now(),
        ]);

        return response()->json([
            'status'     => 'ok',
            'session_id' => $session->id,
        ]);
    }


    public function close(Request $request): JsonResponse
    {
        $user = auth('api')->user() ?? auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $validated = $request->validate([
            'session_id' => 'required|integer',
        ]);

        $session = GameSession::where('id', $validated['session_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return response()->json(['message' => 'Sessão não encontrada'], 404);
        }

        if ($session->status === GameSession::STATUS_ACTIVE) {
            $session->update([
                'status'    => GameSession::STATUS_CLOSED,
                'closed_at' => now(),
            ]);
        }

        return response()->json([
            'status'     => 'closed',
            'session_id' => $session->id,
        ]);
    }

    private function detectDevice(?string $ua): string
    {
        $ua = strtolower($ua ?? '');

        if (str_contains($ua, 'android')) return 'android';
        if (str_contains($ua, 'iphone'))  return 'iphone';
        if (str_contains($ua, 'ipad'))    return 'ipad';
        if (str_contains($ua, 'ios') || (str_contains($ua, 'mac os') && str_contains($ua, 'mobile'))) {
            return 'ios';
        }

        return 'desktop';
    }
}
