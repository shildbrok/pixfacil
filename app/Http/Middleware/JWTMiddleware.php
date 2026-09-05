<?php



namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use JWTAuth;

class JWTMiddleware
{

    public function handle(Request $request, Closure $next): Response
    {
        if (auth('api')->check()) {
            $user = auth('api')->user();
            if ($user && ((int) ($user->banned ?? 0) === 1
                || ! in_array((string) ($user->status ?? 'active'), ['active', '1'], true))) {
                auth('api')->logout();
                return response()->json(['error' => 'Conta bloqueada.'], 403);
            }
            return $next($request);
        }


        return response()->json(['error' => 'Faça login ou cadastre-se para continuar'], 401);
    }
}