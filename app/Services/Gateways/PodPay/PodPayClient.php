<?php

namespace App\Services\Gateways\PodPay;

use App\Models\Gateway;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP do PodPay.
 *
 * Autenticação simples: header `x-api-key` (sk_live_… em produção, sk_test_… no
 * sandbox). Não há token a renovar.
 *
 * ⚠️ TODOS os valores monetários deste provedor são INTEIROS EM CENTAVOS.
 * A conversão fica no PodPayMoney — nunca fazer a conta solta por aí.
 */
class PodPayClient
{
    public const BASE_URL    = 'https://api.podpay.app';
    public const SANDBOX_URL = 'https://sandbox.podpay.app';

    private function gateway(): ?Gateway
    {
        return Gateway::first();
    }

    public function baseUrl(): string
    {
        $uri = (string) ($this->gateway()?->podpay_uri ?: self::BASE_URL);

        return rtrim(trim($uri), '/');
    }

    public function isConfigured(): bool
    {
        return filled($this->gateway()?->podpay_api_key);
    }

    private function http(?string $idempotencyKey = null)
    {
        $headers = ['x-api-key' => (string) ($this->gateway()?->podpay_api_key ?? '')];

        // Evita duplicidade se a mesma intenção for reenviada (timeout de rede).
        if ($idempotencyKey !== null) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        return Http::baseUrl($this->baseUrl())
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->withHeaders($headers)
            ->asJson()->acceptJson()
            ->timeout(25);
    }

    /** Cria a cobrança (PIX). Valores em CENTAVOS. */
    public function createTransaction(array $payload, string $idempotencyKey)
    {
        return $this->http($idempotencyKey)->post('/v1/transactions', $payload);
    }

    /**
     * Consulta a transação na FONTE.
     *
     * É o que fecha o loop de segurança: o webhook do PodPay ainda não é
     * assinado (o campo `signature` vem vazio, é roadmap deles), então o payload
     * sozinho não prova nada e nada é creditado antes de conferir aqui.
     */
    public function getTransaction(string $id)
    {
        return $this->http()->get('/v1/transactions/' . $id);
    }

    /** Solicita o saque. Valores em CENTAVOS. */
    public function createWithdrawal(array $payload, string $idempotencyKey)
    {
        return $this->http($idempotencyKey)->post('/v1/withdrawals', $payload);
    }

    public function getWithdrawal(string $id)
    {
        return $this->http()->get('/v1/withdrawals/' . $id);
    }
}
