<?php



namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSession
{

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        $sessionTokenDb     = (string) ($user->session_token ?? '');
        $sessionTokenHeader = (string) ($request->header('X-Session-Token') ?? '');


        if ($sessionTokenDb === '') {
            auth('api')->logout();
            return response()->json(['error' => 'Sessão inválida. Faça login novamente.'], 401);
        }


        if ($sessionTokenHeader === '') {
            auth('api')->logout();
            return response()->json(['error' => 'Sessão inválida. Faça login novamente.'], 401);
        }


        if (! hash_equals($sessionTokenDb, $sessionTokenHeader)) {
            auth('api')->logout();

            return response()->json([
                'error' => 'Sessão expirada ou iniciada em outro dispositivo. Faça login novamente.',
            ], 401);
        }

        return $next($request);
    }
}
