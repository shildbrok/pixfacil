<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPodPayWebhook;
use App\Models\GatewayWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe os webhooks do PodPay.
 *
 * Guarda o payload cru e responde 200 na hora — o provedor reenvia até 5x se
 * não receber ACK. Toda a decisão (inclusive reconsultar a transação na fonte
 * antes de creditar) fica no ProcessPodPayWebhook.
 */
class PodPayController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $data = (array) $request->input('data', []);

        $log = GatewayWebhookLog::create([
            'gateway'     => 'podpay',
            'channel'     => str_starts_with((string) $request->input('event'), 'withdrawal.') ? 'cashout' : 'cashin',
            'provider_id' => $data['id'] ?? null,
            'external_id' => $request->input('eventId'),
            'ip'          => $request->ip(),
            'payload'     => $request->all(),
        ]);

        // Sem worker de fila nesta instalação: roda após a resposta.
        ProcessPodPayWebhook::dispatchAfterResponse($log->id);

        return response()->json(['received' => true]);
    }
}
