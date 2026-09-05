<?php

namespace App\Services\Gateways\PodPay;

/**
 * Conversão reais <-> centavos do PodPay.
 *
 * Existe como classe própria de propósito: é o ponto onde um erro vira 100x no
 * valor do dinheiro. Toda conversão do PodPay passa por aqui, e só por aqui.
 *
 * Nossa base guarda REAIS (price = 100.00). O PodPay fala CENTAVOS (10000).
 */
class PodPayMoney
{
    /** 100.00 (reais) -> 10000 (centavos). */
    public static function toCents(float $reais): int
    {
        // round antes do cast: (int)(0.29 * 100) dá 28 em float binário.
        return (int) round($reais * 100);
    }

    /** 10000 (centavos) -> 100.00 (reais). */
    public static function toReais(int|float|string $cents): float
    {
        return round(((int) $cents) / 100, 2);
    }
}
