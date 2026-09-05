<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDigitoPayWebhook;
use App\Models\GatewayWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe os webhooks do DigitoPay.
 *
 * Faz o mínimo: guarda o payload cru (RO-10) e responde 200 na hora. Toda a
 * decisão — inclusive reconsultar a transação na fonte antes de creditar — fica
 * no ProcessDigitoPayWebhook. Segurar o provedor esperando o processamento só
 * geraria timeout e reenvio.
 *
 * Endpoints separados por canal (cashin/cashout) porque o mesmo status significa
 * coisas diferentes em cada um: 'EM PROCESSAMENTO' é estorno em andamento no
 * cash in, e fila de pagamento no cash out.
 */
class DigitoPayController extends Controller
{
    public function handle(Request $request, string $channel): JsonResponse
    {
        $log = GatewayWebhookLog::create([
            'gateway'     => 'digitopay',
            'channel'     => $channel,
            'provider_id' => $request->input('id'),
            'external_id' => $request->input('idempotencyKey'),
            'ip'          => $request->ip(),
            'payload'     => $request->all(),
        ]);

        // Sem worker de fila nesta instalação: roda após a resposta, no mesmo processo.
        ProcessDigitoPayWebhook::dispatchAfterResponse($log->id);

        return response()->json(['received' => true]);
    }
}
