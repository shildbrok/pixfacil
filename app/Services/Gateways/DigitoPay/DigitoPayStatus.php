<?php

namespace App\Services\Gateways\DigitoPay;

/**
 * Status do DigitoPay — strings em PORTUGUÊS, MAIÚSCULAS, exatamente como o
 * provedor manda: `ANALISE` sem acento e `EM PROCESSAMENTO` com espaço.
 * Nunca normalizar/slugificar para comparar: o valor tem que bater literal.
 *
 * Cuidado: `EM PROCESSAMENTO` significa coisas DIFERENTES conforme o tipo —
 * no cash in é estorno em andamento; no cash out é fila de pagamento. Por isso
 * nunca se decide por status sem saber se é depósito ou saque.
 */
enum DigitoPayStatus: string
{
    case Pendente        = 'PENDENTE';
    case EmProcessamento = 'EM PROCESSAMENTO';
    case Analise         = 'ANALISE';
    case Erro            = 'ERRO';
    case Cancelado       = 'CANCELADO';
    case Realizado       = 'REALIZADO';

    /** Só REALIZADO e CANCELADO são finais; o resto ainda pode mudar. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Realizado, self::Cancelado], true);
    }

    /** Status desconhecido não explode: vira null e o chamador decide (e loga). */
    public static function parse(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(trim($value));
    }
}
