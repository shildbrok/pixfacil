<?php



namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDefaultLanguage
{

    public function handle($request, Closure $next)
    {
        if (auth('api')->check()) {
            app()->setLocale(auth('api')->user()->language);
        }

        return $next($request);
    }
}
