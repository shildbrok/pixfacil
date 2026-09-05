<?php

namespace App\Services\Gateways;

use App\Helpers\Core;
use App\Services\Gateways\Contracts\PaymentGateway;
use App\Services\Gateways\Drivers\AbilityPayGateway;
use App\Services\Gateways\Drivers\DigitoPayGateway;
use App\Services\Gateways\Drivers\ForceOnePayGateway;
use App\Services\Gateways\Drivers\GeraPixGateway;
use App\Services\Gateways\Drivers\PixupGateway;
use App\Services\Gateways\Drivers\PodPayGateway;

/**
 * Resolve QUAL gateway está ativo.
 *
 * Um ativo por vez, por operação:
 *   settings.deposit_gateway -> depósitos
 *   settings.saque           -> saques
 *
 * As duas colunas já existiam no banco (semeadas com 'gerapix') e nunca eram
 * lidas — este manager é quem finalmente as usa. Se vier vazio ou com um valor
 * desconhecido, cai no GeraPix: o comportamento de antes, nunca uma falha.
 */
class GatewayManager
{
    /** Driver padrão quando a configuração está vazia/inválida. */
    public const FALLBACK = 'gerapix';

    /** @return array<string,class-string<PaymentGateway>> */
    public static function drivers(): array
    {
        return [
            'gerapix'   => GeraPixGateway::class,
            'digitopay' => DigitoPayGateway::class,
            'pixup'     => PixupGateway::class,
            'podpay'    => PodPayGateway::class,
            'abilitypay'=> AbilityPayGateway::class,
            'forceonepay'=> ForceOnePayGateway::class,
        ];
    }

    /** Opções para o seletor do admin: ['gerapix' => 'GeraPix', ...] */
    public static function options(): array
    {
        $out = [];
        foreach (self::drivers() as $key => $class) {
            $out[$key] = app($class)->label();
        }

        return $out;
    }

    /**
     * Nome de exibição de uma chave, para telas que só mostram o gateway atual.
     * Chave vazia/desconhecida devolve o rótulo do fallback — que é exatamente
     * quem vai processar o pagamento nesse caso, então a tela não mente.
     */
    public static function labelFor(?string $key): string
    {
        return self::make($key)->label();
    }

    /** Instancia um driver pela chave. Chave desconhecida -> fallback. */
    public static function make(?string $key): PaymentGateway
    {
        $drivers = self::drivers();
        $key = strtolower(trim((string) $key));

        if (! isset($drivers[$key])) {
            $key = self::FALLBACK;
        }

        return app($drivers[$key]);
    }

    /** Gateway ativo para DEPÓSITO. */
    public static function forDeposit(): PaymentGateway
    {
        return self::make(Core::getSetting()?->deposit_gateway);
    }

    /** Gateway ativo para SAQUE. */
    public static function forWithdrawal(): PaymentGateway
    {
        return self::make(Core::getSetting()?->saque);
    }

    /**
     * O gateway ativo está ligado? Mantém a flag por gateway ({key}_is_enable),
     * que é como o gerapix_is_enable já funcionava — desligar continua sendo
     * possível sem trocar o seletor.
     */
    public static function isEnabled(PaymentGateway $gateway): bool
    {
        $flag = $gateway->key() . '_is_enable';

        return (bool) (Core::getSetting()?->{$flag} ?? false);
    }
}
