<?php

namespace App\Services\Gateways\ForceOnePay;

use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP do ForceOnePay.
 *
 * A API tem apenas 4 rotas: emitir token, criar PIX, transferir PIX e saldo.
 * NÃO existe consulta de transação nem estorno — o que muda todo o desenho de
 * segurança deste gateway (ver ForceOnePayGateway).
 */
class ForceOnePayClient
{
    private const TOKEN_KEY = 'forceonepay:access_token';
    public const BASE_URL   = 'https://api.forceonepay.com.br';

    private function gateway(): ?Gateway
    {
        return Gateway::first();
    }

    public function baseUrl(): string
    {
        $uri = (string) ($this->gateway()?->forceonepay_uri ?: self::BASE_URL);

        return rtrim(trim($uri), '/');
    }

    public function isConfigured(): bool
    {
        return filled($this->gateway()?->forceonepay_token);
    }

    /**
     * `status` do provedor é type-inconsistente: vem 1 (int) nos endpoints e
     * true (bool) nos webhooks; erro vem 0. Comparar com === 1 quebraria em
     * metade dos casos — a própria doc alerta sobre isso.
     */
    public static function isOk(mixed $status): bool
    {
        return $status === true || $status === 1 || $status === '1';
    }

    /**
     * Token de trabalho, emitido a partir do token CONTRATADO.
     *
     * A resposta traz `expirate` ("2026-01-01 01:01:01") mas SEM fuso horário
     * documentado — interpretar errado daria um token vencido ou eterno. Por
     * isso usamos TTL fixo curto e tratamos 401 renovando uma vez.
     */
    private function token(bool $fresh = false): ?string
    {
        if ($fresh) {
            Cache::forget(self::TOKEN_KEY);
        }

        return Cache::remember(self::TOKEN_KEY, now()->addMinutes(30), function (): ?string {
            $contratado = (string) ($this->gateway()?->forceonepay_token ?? '');
            if ($contratado === '') {
                return null;
            }

            $res = Http::withOptions(['force_ip_resolve' => 'v4'])
                ->withToken($contratado)
                ->asJson()->acceptJson()->timeout(15)
                ->post($this->baseUrl() . '/api/authorization/bearer');

            if (! $res->successful() || ! self::isOk($res->json('status'))) {
                return null;
            }

            return $res->json('data.token');
        });
    }

    private function http(bool $fresh = false)
    {
        return Http::baseUrl($this->baseUrl())
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->withToken((string) $this->token($fresh))
            ->asJson()->acceptJson()
            ->timeout(25);
    }

    /** Executa e, em 401/403, repete UMA vez com token novo. Sem loop. */
    private function send(callable $call)
    {
        $res = $call($this->http());

        if (in_array($res->status(), [401, 403], true)) {
            $res = $call($this->http(fresh: true));
        }

        return $res;
    }

    /** Cash-In. Valor em reais (decimal com ponto). */
    public function criarPix(array $payload)
    {
        return $this->send(fn ($h) => $h->post('/api/pix/criar', $payload));
    }

    /**
     * Cash-Out.
     *
     * SEM retry automático aqui de propósito: a política de idempotência da API
     * é "não informada" na doc, então um reenvio pode virar um SEGUNDO pagamento.
     * Um timeout deixa o saque em processamento e o webhook decide.
     */
    public function transferirPix(array $payload)
    {
        return $this->http()->post('/api/pix/transferir', $payload);
    }

    public function balance()
    {
        return $this->send(fn ($h) => $h->get('/api/usuario/balance'));
    }
}
