<?php



namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;


class CaptureBetCrmRef
{
    public const COOKIE = 'betcrm_ref';


    private const PARAMS = [
        'bc_ref'       => 'betcrm_ref',
        'utm_source'   => 'betcrm_utm_source',
        'utm_medium'   => 'betcrm_utm_medium',
        'utm_campaign' => 'betcrm_utm_campaign',
        'fbclid'       => 'betcrm_fbclid',
        'gclid'        => 'betcrm_gclid',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::PARAMS as $param => $cookie) {
            $value = $request->query($param);


            if ($param === 'bc_ref' && ($value === null || $value === '')) {
                $value = $request->query('bc');
            }


            if (
                is_string($value) && $value !== '' && strlen($value) <= 200
                && preg_match('/^[A-Za-z0-9._\-]+$/', $value)
                && ! $request->cookies->has($cookie)
            ) {
                $response->headers->setCookie(new Cookie(
                    $cookie,
                    $value,
                    now()->addDays(90),
                    '/',
                    null,
                    $request->isSecure(), 
                    false, 
                    false,
                    Cookie::SAMESITE_LAX
                ));
            }
        }

        return $response;
    }
}