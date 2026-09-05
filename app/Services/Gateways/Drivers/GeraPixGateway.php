<?php

namespace App\Services\Gateways\Drivers;

use App\Models\Gateway;
use App\Services\Gateways\Contracts\PaymentGateway;
use App\Traits\Gateways\GeraPixTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Driver do GeraPix.
 *
 * DE PROPÓSITO ele só embrulha o GeraPixTrait, sem reescrever nada: o trait é o
 * caminho de dinheiro que já roda em produção há tempo. Trazer a lógica para cá
 * seria reescrever depósito, saque e webhook de uma vez — risco enorme por
 * ganho nenhum. O contrato é o que muda; o comportamento é bit a bit o mesmo.
 */
class GeraPixGateway implements PaymentGateway
{
    use GeraPixTrait;

    public function key(): string
    {
        return 'gerapix';
    }

    public function label(): string
    {
        return 'GeraPix';
    }

    public function isConfigured(): bool
    {
        $gateway = Gateway::first();

        return $gateway && filled($gateway->gerapix_uri) && filled($gateway->gerapix_secret_token);
    }

    public function createDeposit(Request $request): JsonResponse
    {
        return $this->requestQrcodeGeraPix($request);
    }

    public function cashOut(int $withdrawalId, ?string $pixType = null): bool
    {
        return (bool) $this->pixCashOutGeraPix($withdrawalId, $pixType);
    }

    /** O GeraPix só paga saque para CPF/CNPJ — ver GeraPixTrait::pixCashOutGeraPix. */
    public function supportedPixTypes(): array
    {
        return ['document'];
    }
}
