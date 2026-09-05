<?php

namespace App\Services\Gateways\AbilityPay;

use App\Models\Gateway;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP do AbilityPay.
 *
 * Autenticação por par de headers (X-Client-Id + X-Client-Secret) — não há
 * token a emitir nem renovar. Valores em REAIS (não centavos).
 */
class AbilityPayClient
{
    public const BASE_URL = 'https://abilitypay.app/api';

    private function gateway(): ?Gateway
    {
        return Gateway::first();
    }

    public function baseUrl(): string
    {
        $uri = (string) ($this->gateway()?->abilitypay_uri ?: self::BASE_URL);

        return rtrim(trim($uri), '/');
    }

    public function isConfigured(): bool
    {
        $g = $this->gateway();

        return $g && filled($g->abilitypay_client_id) && filled($g->abilitypay_client_secret);
    }

    private function http(?string $idempotencyKey = null)
    {
        $g = $this->gateway();

        $headers = [
            'X-Client-Id'     => (string) ($g?->abilitypay_client_id ?? ''),
            'X-Client-Secret' => (string) ($g?->abilitypay_client_secret ?? ''),
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::baseUrl($this->baseUrl())
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->withHeaders($headers)
            ->asJson()->acceptJson()
            ->timeout(25);
    }

    /** Cobrança PIX. Valor em REAIS. Devolve external_id, pix_code, etc. */
    public function createCharge(array $payload)
    {
        return $this->http()->post('/integrations/pix/charges', $payload);
    }

    /**
     * Consulta a transação na FONTE, pelo external_id.
     *
     * É o que fecha o loop de segurança: o webhook do AbilityPay não tem
     * assinatura nenhuma, então o payload sozinho não prova nada e nada é
     * creditado antes de conferir aqui.
     */
    public function getTransaction(string $externalId)
    {
        return $this->http()->get('/integrations/transactions/' . $externalId);
    }

    /** Saque PIX. O Idempotency-Key é OBRIGATÓRIO neste provedor. */
    public function createWithdrawal(array $payload, string $idempotencyKey)
    {
        return $this->http($idempotencyKey)->post('/integrations/withdrawals', $payload);
    }

    public function balance()
    {
        return $this->http()->get('/integrations/balance');
    }
}
