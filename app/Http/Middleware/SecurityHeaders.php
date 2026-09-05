<?php



namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        $existingCsp = (string) $response->headers->get('Content-Security-Policy', '');
        if ($existingCsp === '') {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self' https: data: blob:; "
                . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:; "
                . "style-src 'self' 'unsafe-inline' https:; "
                . "img-src 'self' data: blob: https:; "
                . "font-src 'self' data: https:; "
                . "connect-src 'self' https: wss: ws:; "
                . "frame-ancestors 'self'; base-uri 'self'; form-action 'self' https:; object-src 'none';"
            );
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
