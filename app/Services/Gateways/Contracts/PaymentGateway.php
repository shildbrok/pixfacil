<?php

namespace App\Services\Gateways\Contracts;

use Illuminate\Http\Request;

/**
 * Contrato comum dos gateways de pagamento.
 *
 * Só um gateway fica ativo por vez para depósito (settings.deposit_gateway) e
 * um para saque (settings.saque). O resolvedor é o GatewayManager.
 *
 * O que este contrato NÃO faz: creditar saldo. Quem credita é sempre o
 * DepositPaymentFinalizer, igual para todos os gateways — é ele que tem o lock,
 * a trava de idempotência (status 0->1) e as regras de bônus/rollover/CPA.
 * Um driver só fala com o provedor e traduz o resultado.
 */
interface PaymentGateway
{
    /** Chave curta e estável do gateway (ex.: 'gerapix'). Vai para a coluna `gateway`. */
    public function key(): string;

    /** Nome de exibição no admin. */
    public function label(): string;

    /** Credenciais preenchidas? Não diz se está ativo — só se dá para usar. */
    public function isConfigured(): bool;

    /**
     * Cria a cobrança PIX e devolve a resposta JSON para o front.
     *
     * O front espera exatamente {status, idTransaction, qrcode} — `qrcode` é o
     * copia-e-cola (EMV); o QR é desenhado no navegador a partir dele.
     * Mudar esse shape quebra DepositPage.vue e DepositWidget.vue.
     */
    public function createDeposit(Request $request): \Illuminate\Http\JsonResponse;

    /**
     * Envia o PIX de saque. Retorna true se o provedor aceitou.
     *
     * A idempotência é responsabilidade do driver: reenvio do MESMO saque tem
     * que reutilizar a mesma referência externa, senão o provedor paga duas vezes.
     */
    public function cashOut(int $withdrawalId, ?string $pixType = null): bool;

    /**
     * Tipos de chave PIX que este gateway aceita no saque.
     * O GeraPix, por exemplo, só aceita documento (CPF/CNPJ).
     *
     * @return array<int,string> ex.: ['document'] ou ['document','email','phone','random']
     */
    public function supportedPixTypes(): array;
}
