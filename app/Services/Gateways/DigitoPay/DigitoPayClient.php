<?php

namespace App\Services\Gateways\DigitoPay;

use App\Models\Gateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP do DigitoPay.
 *
 * As credenciais vêm do banco (tabela gateways), não do .env — é o padrão que o
 * GeraPix já usa aqui e é o que permite white-label: cada cliente opera com a
 * conta dele, configurada pelo admin. (A doc oficial sugere config/env; não dá
 * para seguir isso num sistema distribuído a vários operadores.)
 */
class DigitoPayClient
{
    private const TOKEN_KEY  = 'digitopay:access_token';
    public const BASE_URL    = 'https://api.digitopayoficial.com.br';

    private function gateway(): ?Gateway
    {
        return Gateway::first();
    }

    public function baseUrl(): string
    {
        $uri = (string) ($this->gateway()?->digitopay_uri ?: self::BASE_URL);

        return rtrim(trim($uri), '/');
    }

    public function isConfigured(): bool
    {
        $g = $this->gateway();

        return $g && filled($g->digitopay_client_id) && filled($g->digitopay_secret);
    }

    /**
     * O token vale 30 min. Cacheamos por 25 para nunca usar um token expirando
     * na virada. O campo `expiration` da resposta vem em ticks .NET (não é unix
     * timestamp) — de propósito não parseamos: TTL fixo é mais seguro que
     * interpretar errado.
     */
    private function token(bool $fresh = false): ?string
    {
        if ($fresh) {
            Cache::forget(self::TOKEN_KEY);
        }

        return Cache::remember(self::TOKEN_KEY, now()->addMinutes(25), function (): ?string {
            $g = $this->gateway();
            if (! $g) {
                return null;
            }

            $res = Http::withOptions(['force_ip_resolve' => 'v4'])
                ->asJson()->acceptJson()->timeout(15)
                ->post($this->baseUrl() . '/api/token/api', [
                    'clientId' => (string) $g->digitopay_client_id,
                    'secret'   => (string) $g->digitopay_secret,
                ]);

            return $res->successful() ? $res->json('accessToken') : null;
        });
    }

    private function http(bool $fresh = false): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())   // sem isto, os paths abaixo saem relativos e não chegam a lugar nenhum
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->withToken((string) $this->token($fresh))
            ->asJson()->acceptJson()
            ->timeout(25);
    }

    /**
     * Executa e, em 401, repete UMA vez com token novo (o token pode ter
     * expirado entre o cache e a chamada). Uma vez só: loop de retry com
     * credencial ruim viraria martelada no provedor.
     */
    private function send(callable $call)
    {
        $res = $call($this->http());

        if ($res->status() === 401) {
            $res = $call($this->http(fresh: true));
        }

        return $res;
    }

    /** Cash In. Devolve ['id','pixCopiaECola','qrCodeBase64','success']. */
    public function deposit(array $payload)
    {
        return $this->send(fn (PendingRequest $h) => $h->post('/api/deposit', $payload));
    }

    /** Cash Out. */
    public function withdraw(array $payload)
    {
        return $this->send(fn (PendingRequest $h) => $h->post('/api/withdraw', $payload));
    }

    /**
     * A rota mais importante da integração: é ela que fecha o loop de segurança.
     * O webhook do DigitoPay não tem assinatura, então nada é creditado antes de
     * confirmar o status aqui, na fonte (RO-1).
     */
    public function getTransaction(?string $id = null, ?string $idempotencyKey = null)
    {
        $query = array_filter([
            'id'             => $id,
            'idempotencyKey' => $idempotencyKey,
        ]);

        return $this->send(fn (PendingRequest $h) => $h->get('/api/getTransaction', $query));
    }

    /** Estorna um cash in — usado quando a titularidade não bate (RO-7). */
    public function refund(string $providerId)
    {
        return $this->send(fn (PendingRequest $h) => $h->post('/api/refund', [
            'id'             => $providerId,
            'refundExternal' => true,
        ]));
    }

    /** Valida chave PIX no DICT. Consulta limitada pelo Bacen — não usar em massa. */
    public function getPixKey(string $pixKey, string $pixType)
    {
        return $this->send(fn (PendingRequest $h) => $h->get('/api/getPixKey', [
            'pixKey'  => $pixKey,
            'pixType' => $pixType,
        ]));
    }
}
