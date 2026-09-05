<?php

namespace App\Services\Gateways\Pixup;

use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cliente HTTP do PIXUP.
 *
 * Duas camadas de autenticação:
 *   1. Bearer OAuth2 (client_credentials) em TODAS as rotas.
 *   2. HMAC adicional só nas rotas FINANCEIRAS (cashout). Cash-in não assina.
 *
 * Credenciais vêm do banco (tabela gateways), como os demais gateways daqui —
 * é o que permite cada operador white-label usar a conta dele.
 */
class PixupClient
{
    private const TOKEN_KEY = 'pixup:access_token';
    public const BASE_URL   = 'https://api.pixupbr.com';

    private function gateway(): ?Gateway
    {
        return Gateway::first();
    }

    public function baseUrl(): string
    {
        $uri = (string) ($this->gateway()?->pixup_uri ?: self::BASE_URL);

        return rtrim(trim($uri), '/');
    }

    public function isConfigured(): bool
    {
        $g = $this->gateway();

        return $g && filled($g->pixup_client_id) && filled($g->pixup_client_secret);
    }

    /**
     * Token OAuth2. Vale 1h e não há revogação — cacheamos por 55min para nunca
     * usar um token virando na hora da chamada.
     */
    private function token(bool $fresh = false): ?string
    {
        if ($fresh) {
            Cache::forget(self::TOKEN_KEY);
        }

        return Cache::remember(self::TOKEN_KEY, now()->addMinutes(55), function (): ?string {
            $g = $this->gateway();
            if (! $g) {
                return null;
            }

            $basic = base64_encode(((string) $g->pixup_client_id) . ':' . ((string) $g->pixup_client_secret));

            $res = Http::withOptions(['force_ip_resolve' => 'v4'])
                ->withHeaders(['Authorization' => 'Basic ' . $basic])
                ->asJson()->acceptJson()->timeout(15)
                ->post($this->baseUrl() . '/v2/oauth/token', ['grant_type' => 'client_credentials']);

            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    private function base(bool $fresh = false)
    {
        return Http::baseUrl($this->baseUrl())
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->withToken((string) $this->token($fresh))
            ->acceptJson()
            ->timeout(25);
    }

    /** Cash-in: sem HMAC. */
    public function cashIn(array $payload)
    {
        $call = fn (bool $fresh = false) => $this->base($fresh)->asJson()->post('/v2/transactions/cashin', $payload);

        $res = $call();

        return $res->status() === 401 ? $call(true) : $res;
    }

    /**
     * Cash-out.
     *
     * Sem HMAC: a doc (dev.pixupbr.com/payments/cashout) descreve X-Signature com
     * uma `signing_key`, mas esse campo não existe no painel de contas reais —
     * não há de onde tirar a chave. Se algum dia aparecer, é aqui que entra.
     */
    public function cashOut(array $payload)
    {
        $call = fn (bool $fresh = false) => $this->base($fresh)->asJson()->post('/v2/transactions/cashout', $payload);

        $res = $call();

        return $res->status() === 401 ? $call(true) : $res;
    }

    /** Extrato — usado para reconciliação, não para creditar. */
    public function listTransactions(array $filters)
    {
        $call = fn (bool $fresh = false) => $this->base($fresh)->asJson()
            ->post('/v2/account/transactions/list', $filters);

        $res = $call();

        return $res->status() === 401 ? $call(true) : $res;
    }

    /** Segredo do webhook configurado? Sem ele, nada é aceito. */
    public function hasWebhookSecret(): bool
    {
        return filled($this->gateway()?->pixup_webhook_secret);
    }

    /**
     * Valida o HMAC de um webhook que chegou.
     *   X-Webhook-Signature: hex(hmac_sha256(rawBody, webhook_secret))
     *
     * Usa o body CRU (bytes), não o JSON já parseado: qualquer reordenação de
     * chaves ou mudança de espaçamento muda o hash. hash_equals para comparação
     * em tempo constante, e janela de ±5 min contra replay.
     *
     * O `webhook_secret` é o mesmo valor configurado no dashboard da PIXUP —
     * se os dois lados divergirem, NENHUM depósito é confirmado.
     */
    public function verifyWebhook(string $rawBody, ?string $signature, ?string $timestamp): bool
    {
        $secret = (string) ($this->gateway()?->pixup_webhook_secret ?? '');

        if ($secret === '' || ! filled($signature)) {
            return false;
        }

        if (filled($timestamp) && abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), (string) $signature);
    }
}
