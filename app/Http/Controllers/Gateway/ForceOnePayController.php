<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\GatewayWebhookLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\ForceOnePay\ForceOnePayClient;
use App\Services\Payments\DepositPaymentFinalizer;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Webhook do ForceOnePay.
 *
 * Este provedor não tem assinatura de webhook nem consulta de transação. A
 * autenticidade vem do SEGREDO no caminho da URL: cada transação recebe uma URL
 * única, gerada por nós e enviada ao provedor na requisição. Quem não conhece o
 * segredo não chega aqui — e o segredo identifica exatamente qual transação é,
 * o que também resolve a ambiguidade do `idTransaction` (a doc não diz se ele é
 * o txid ou o codigo).
 *
 * O crédito continua sendo do DepositPaymentFinalizer, com o mesmo lock e a
 * mesma trava 0->1 dos outros gateways: webhook repetido vira no-op.
 */
class ForceOnePayController extends Controller
{
    /** Depósito: /forceonepay/callback/{secret} */
    public function cashIn(Request $request, string $secret): JsonResponse
    {
        $log = $this->log('cashin', $request, $secret);

        // O segredo é a credencial: sem transação correspondente, é lixo ou
        // tentativa de forja. Não vaza qual dos dois na resposta.
        $tx = Transaction::where('idUnico', $secret)
            ->where('gateway', 'forceonepay')
            ->first();

        if (! $tx) {
            GatewayLog::warning('FORCEONEPAY', 'webhook com segredo desconhecido', 404, ['ip' => $request->ip()]);
            $log->update(['error' => 'segredo desconhecido', 'processed_at' => now()]);

            return response()->json(['error' => 'not found'], 404);
        }

        $data = (array) $request->input('data', []);

        // `status` vem true no webhook e 1 nos endpoints — isOk trata os dois.
        if (! ForceOnePayClient::isOk($request->input('status'))) {
            // Corpo de falha não é documentado. NÃO marcamos como cancelado no
            // chute: mandamos para revisão em vez de fechar a transação errada.
            GatewayLog::warning('FORCEONEPAY', 'webhook de cash-in não-sucesso — revisar à mão', null, [
                'txid' => $tx->payment_id, 'message' => $request->input('message'),
            ]);
            $log->update(['error' => 'status não-sucesso: revisar', 'processed_at' => now()]);

            return response()->json(['received' => true]);
        }

        // O txid do payload tem que ser o mesmo da transação daquele segredo.
        $txid = (string) ($data['txid'] ?? '');
        if ($txid !== '' && $txid !== (string) $tx->payment_id) {
            GatewayLog::warning('FORCEONEPAY', 'txid do webhook não bate com o da transação', null, [
                'esperado' => $tx->payment_id, 'recebido' => $txid,
            ]);
            $log->update(['error' => 'txid divergente', 'processed_at' => now()]);

            return response()->json(['error' => 'mismatch'], 409);
        }

        // Valor: o webhook manda number (100.50); o síncrono manda string.
        // O valor que vale é o da NOSSA base — o do payload só precisa bater.
        $esperado = (float) $tx->price;
        $recebido = (float) ($data['amount'] ?? 0);

        if ($recebido > 0 && abs($recebido - $esperado) > 0.01) {
            GatewayLog::warning('FORCEONEPAY', 'valor divergente — não creditado', null, [
                'txid' => $txid, 'esperado' => $esperado, 'webhook' => $recebido,
            ]);
            $log->update(['error' => 'valor divergente', 'processed_at' => now()]);

            return response()->json(['error' => 'amount mismatch'], 409);
        }

        DepositPaymentFinalizer::finalize((string) $tx->payment_id, $secret, null, 'forceonepay');

        $log->update(['processed_at' => now()]);

        return response()->json(['received' => true]);
    }

    /** Saque: /forceonepay/callback/wd/{uuid} */
    public function cashOut(Request $request, string $uuid): JsonResponse
    {
        $log = $this->log('cashout', $request, $uuid);

        $w = Withdrawal::where('payment_id', $uuid)
            ->where('gateway', 'forceonepay')
            ->first();

        if (! $w) {
            GatewayLog::warning('FORCEONEPAY', 'webhook de saque com uuid desconhecido', 404, ['ip' => $request->ip()]);
            $log->update(['error' => 'uuid desconhecido', 'processed_at' => now()]);

            return response()->json(['error' => 'not found'], 404);
        }

        // Já finalizado: webhook repetido ou fora de ordem vira no-op.
        if (in_array((int) $w->status, [1, 2], true)) {
            $log->update(['processed_at' => now()]);

            return response()->json(['received' => true]);
        }

        if (ForceOnePayClient::isOk($request->input('status'))) {
            $w->update(['status' => 1]);
        } else {
            // Corpo de falha não documentado: em vez de estornar no chute, deixa
            // em processamento e registra para conferência humana. Estornar um
            // saque que na verdade saiu daria saldo de graça ao jogador.
            GatewayLog::warning('FORCEONEPAY', 'webhook de saque não-sucesso — revisar à mão', null, [
                'withdrawal' => $w->id, 'message' => $request->input('message'),
            ]);
            $log->update(['error' => 'status não-sucesso: revisar']);
        }

        $log->update(['processed_at' => now()]);

        return response()->json(['received' => true]);
    }

    /**
     * Registra o payload cru para auditoria (a doc recomenda explicitamente).
     * O segredo NÃO é gravado: ele é credencial, e este log é lido no admin.
     */
    private function log(string $channel, Request $request, string $secret): GatewayWebhookLog
    {
        $data = (array) $request->input('data', []);

        return GatewayWebhookLog::create([
            'gateway'     => 'forceonepay',
            'channel'     => $channel,
            'provider_id' => $data['txid'] ?? $data['idTransaction'] ?? null,
            'external_id' => null,
            'ip'          => $request->ip(),
            'payload'     => $request->all(),
        ]);
    }
}
