<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAbilityPayWebhook;
use App\Models\GatewayWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe os webhooks do AbilityPay.
 *
 * Guarda o payload cru e responde 2xx rápido (a doc pede < 5s). Toda a decisão
 * — inclusive reconsultar a transação na fonte antes de creditar — fica no
 * ProcessAbilityPayWebhook.
 *
 * O caminho desta rota é cadastrado no painel DELES, ao criar a chave de API.
 */
class AbilityPayController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $data  = (array) $request->input('data', []);
        $event = (string) $request->input('event', '');

        $log = GatewayWebhookLog::create([
            'gateway'     => 'abilitypay',
            'channel'     => str_starts_with($event, 'payout.') ? 'cashout' : 'cashin',
            'provider_id' => $data['transaction_id'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'ip'          => $request->ip(),
            'payload'     => $request->all(),
        ]);

        // Sem worker de fila nesta instalação: roda após a resposta.
        ProcessAbilityPayWebhook::dispatchAfterResponse($log->id);

        return response()->json(['received' => true]);
    }
}
