<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Proxies confiáveis governados pela flag CLOUDFLARE_ENABLED (ver .env).
 *
 *  - CLOUDFLARE_ENABLED=false (padrão): confia apenas no proxy local/privado
 *    (nginx do CloudPanel + redes privadas). Resolve o IP real do cliente e
 *    NÃO permite forjar X-Forwarded-For. Funciona out-of-the-box, sem configurar nada.
 *
 *  - CLOUDFLARE_ENABLED=true: além do acima, confia nas faixas CIDR da Cloudflare,
 *    para que o IP real do visitante (que a Cloudflare coloca no X-Forwarded-For)
 *    seja lido corretamente atrás dela.
 *
 * Isto substitui o antigo TRUSTED_PROXIES=* (que era forjável).
 */
class TrustProxies extends Middleware
{
    protected $proxies;

    /** Redes privadas / loopback — nunca originam tráfego externo real. */
    private const PRIVATE_RANGES = [
        '127.0.0.1',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    /**
     * Faixas oficiais da Cloudflare (https://www.cloudflare.com/ips).
     * Mudam raramente; atualize se a Cloudflare publicar novas.
     */
    private const CLOUDFLARE_RANGES = [
        // IPv4
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public function __construct()
    {
        $cloudflare = filter_var(config('app.cloudflare_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $this->proxies = $cloudflare
            ? array_merge(self::PRIVATE_RANGES, self::CLOUDFLARE_RANGES)
            : self::PRIVATE_RANGES;
    }

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
