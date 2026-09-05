<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPixupWebhook;
use App\Models\GatewayWebhookLog;
use App\Services\Gateways\Pixup\PixupClient;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe os webhooks do PIXUP.
 *
 * Guarda o payload cru e responde 200 na hora — o provedor reenvia até 5x se não
 * receber ACK. Toda a decisão (inclusive reconsultar a transação na fonte antes
 * de creditar) fica no ProcessPixupWebhook.
 *
 * A assinatura NÃO é exigida: a doc descreve um webhook_secret vindo do painel,
 * mas esse campo não existe em conta real — então nada podia bater e todo
 * depósito era recusado com 401. Quando o segredo estiver configurado dos dois
 * lados, a conferência abaixo volta a valer como reforço.
 */
class PixupController extends Controller
{
    public function __construct(private readonly PixupClient $client)
    {
    }

    public function callback(Request $request): JsonResponse
    {
        // Evento de sandbox não move dinheiro real — a doc manda recusar em
        // produção. Sem isto, um webhook de teste creditaria de verdade.
        if ($request->header('X-Sandbox') === '1' && app()->environment('production')) {
            GatewayLog::warning('PIXUP', 'webhook de sandbox recusado em produção', 400, [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'sandbox'], 400);
        }

        // Só confere quando os DOIS lados têm segredo. Sem isso, recusar seria
        // recusar tudo; e a segurança real está na reconsulta feita pelo job.
        $signature = $request->header('X-Webhook-Signature');

        if ($this->client->hasWebhookSecret() && filled($signature)) {
            // Body CRU: a assinatura é sobre os bytes exatos. Usar $request->all()
            // aqui (JSON já parseado e re-serializado) faria o hash nunca bater.
            $ok = $this->client->verifyWebhook(
                $request->getContent(),
                $signature,
                $request->header('X-Webhook-Timestamp'),
            );

            if (! $ok) {
                GatewayLog::warning('PIXUP', 'webhook com assinatura inválida', 401, [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'assinatura inválida'], 401);
            }
        }

        $payload = (array) $request->json()->all();
        $event   = (string) ($payload['event'] ?? '');
        $data    = (array) ($payload['data'] ?? []);

        $log = GatewayWebhookLog::create([
            'gateway'     => 'pixup',
            'channel'     => str_starts_with($event, 'cashout.') ? 'cashout' : 'cashin',
            'provider_id' => $payload['transaction_id'] ?? $data['transaction_id'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'ip'          => $request->ip(),
            'payload'     => $payload,
        ]);

        // Sem worker de fila nesta instalação: roda após a resposta.
        ProcessPixupWebhook::dispatchAfterResponse($log->id);

        return response()->json(['received' => true]);
    }
}
