<?php

namespace App\Jobs;

use App\Models\GatewayWebhookLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\Pixup\PixupClient;
use App\Services\Payments\DepositPaymentFinalizer;
use App\Support\GatewayLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Processa um webhook do PIXUP.
 *
 * A doc deles descreve um `webhook_secret` "definido no Dashboard" e um
 * X-Webhook-Signature — mas esse campo NÃO existe no painel de contas reais, do
 * mesmo jeito que a `signing_key` do cashout não existe. Na prática o webhook
 * chega sem assinatura verificável, então exigi-la recusava todo depósito.
 *
 * Sem assinatura, o payload é só "a campainha" e NUNCA credita sozinho:
 * reconsultamos a transação na fonte e creditamos pelo que a API diz — mesmo
 * desenho do DigitoPay e do PodPay. Um webhook forjado não vira dinheiro porque
 * quem decide é a API, autenticada com o nosso próprio token OAuth2.
 */
class ProcessPixupWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(private readonly int $logId)
    {
    }

    public function handle(PixupClient $client): void
    {
        $log = GatewayWebhookLog::find($this->logId);
        if (! $log || $log->processed_at) {
            return; // reenvio de webhook é normal: vira no-op
        }

        try {
            $payload = (array) $log->payload;
            $event   = (string) ($payload['event'] ?? '');
            $data    = (array) ($payload['data'] ?? []);

            match (true) {
                str_starts_with($event, 'cashin.')  => $this->handleCashIn($log, $client, $payload, $data),
                str_starts_with($event, 'cashout.') => $this->handleCashOut($log, $client, $data),
                default                             => null, // evento que não movemos: ignora
            };

            $log->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            GatewayLog::exception('PIXUP', 'exceção ao processar webhook', $e, ['log_id' => $log->id]);
            $log->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    private function handleCashIn(GatewayWebhookLog $log, PixupClient $client, array $payload, array $data): void
    {
        $transactionId = (string) ($payload['transaction_id'] ?? $data['transaction_id'] ?? '');

        if ($transactionId === '') {
            $this->fail($log, 'webhook sem transaction_id');

            return;
        }

        $tx = $this->findAtSource($client, ['transaction_id' => $transactionId], 'transaction_id', $transactionId);

        if ($tx === null) {
            $this->fail($log, 'transação não encontrada na fonte (ou consulta falhou)');

            return;
        }

        // external_id da FONTE, não do payload: é ele que amarra a transação à
        // nossa linha, e vindo da API não dá para forjar.
        $externalId = (string) ($tx['external_id'] ?? '');

        $local = Transaction::where('payment_id', $transactionId)
            ->where('idUnico', $externalId)
            ->first();

        if (! $local) {
            $this->fail($log, 'transação não encontrada na base');

            return;
        }

        $status = strtolower(trim((string) ($tx['status'] ?? '')));

        if (in_array($status, ['cancelled', 'canceled', 'failed', 'refunded', 'expired'], true)) {
            // Fecha a cobrança sem creditar.
            if ((int) $local->status === 0) {
                $local->update(['status' => 2, 'gateway_status' => $status]);
            }

            return;
        }

        if ($status !== 'confirmed') {
            // Ainda pendente: não credita. Se o pagamento cair depois, o provedor
            // reenvia e o próximo log reconsulta do zero.
            $local->update(['gateway_status' => $status]);

            return;
        }

        // Valor conferido contra a FONTE. Ela manda string ("10.00"); o cast
        // resolve. O finalizador não faz essa guarda quando $request é null.
        $expected = (float) $local->price;
        $paid     = (float) ($tx['amount'] ?? 0);

        if ($paid > 0 && abs($paid - $expected) > 0.01) {
            GatewayLog::warning('PIXUP', 'valor divergente — não creditado', null, [
                'transaction' => $transactionId, 'esperado' => $expected, 'fonte' => $paid,
            ]);
            $this->fail($log, "valor divergente: esperado {$expected}, fonte {$paid}");

            return;
        }

        // O crédito é do finalizador: mesmo lock e mesma trava 0->1 de todos os
        // gateways. Evento repetido vira no-op.
        DepositPaymentFinalizer::finalize($transactionId, $externalId, null, 'pixup');
    }

    private function handleCashOut(GatewayWebhookLog $log, PixupClient $client, array $data): void
    {
        // O driver grava o external_id que gerou em withdrawals.payment_id — é o
        // elo entre os dois lados.
        $externalId = (string) ($data['external_id'] ?? '');

        if ($externalId === '') {
            $this->fail($log, 'webhook de saque sem external_id');

            return;
        }

        $w = Withdrawal::where('payment_id', $externalId)->first();

        if (! $w) {
            $this->fail($log, 'saque não encontrado na base');

            return;
        }

        // Já finalizado: não regride (webhook fora de ordem acontece).
        if (in_array((int) $w->status, [1, 2], true)) {
            return;
        }

        // Também reconsulta: um `cashout.failed` forjado devolveria ao saldo um
        // saque que na verdade foi pago — isso, sim, criaria dinheiro.
        $tx = $this->findAtSource($client, ['external_id' => $externalId, 'type' => 'cashout'], 'external_id', $externalId);

        if ($tx === null) {
            $this->fail($log, 'saque não encontrado na fonte (ou consulta falhou)');

            return;
        }

        $status = strtolower(trim((string) ($tx['status'] ?? '')));

        match (true) {
            $status === 'confirmed' => $w->update(['status' => 1]),
            in_array($status, ['failed', 'cancelled', 'canceled', 'refunded'], true) => $this->refundWithdrawal($w),
            default => $w->update(['status' => 9]),
        };
    }

    /**
     * Reconsulta uma transação no extrato do PIXUP.
     *
     * Não existe rota de "consultar 1 transação" na v2: o extrato filtrado é o
     * caminho oficial. Confere o campo de volta porque o filtro é do lado deles.
     */
    private function findAtSource(PixupClient $client, array $filters, string $matchField, string $matchValue): ?array
    {
        $res = $client->listTransactions($filters + ['page' => 1, 'page_size' => 20]);

        if (! $res->successful()) {
            GatewayLog::warning('PIXUP', 'consulta na fonte falhou', $res->status(), [
                'body' => mb_substr((string) $res->body(), 0, 300),
            ]);

            return null;
        }

        foreach ((array) ($res->json('data.items') ?? []) as $item) {
            if ((string) (((array) $item)[$matchField] ?? '') === $matchValue) {
                return (array) $item;
            }
        }

        return null;
    }

    /** Saque recusado: devolve ao saldo sacável e cancela, uma única vez. */
    private function refundWithdrawal(Withdrawal $w): void
    {
        DB::transaction(function () use ($w) {
            $fresh = Withdrawal::where('id', $w->id)->lockForUpdate()->first();

            if (! $fresh || in_array((int) $fresh->status, [1, 2], true)) {
                return;
            }

            $wallet = Wallet::where('user_id', $fresh->user_id)->lockForUpdate()->first();
            if ($wallet) {
                $wallet->balance_withdrawal = (float) $wallet->balance_withdrawal + (float) $fresh->amount;
                $wallet->save();
            }

            $fresh->status = 2;
            $fresh->save();
        });
    }

    private function fail(GatewayWebhookLog $log, string $reason): void
    {
        GatewayLog::warning('PIXUP', 'webhook não processado', null, ['motivo' => $reason]);
        $log->update(['error' => $reason, 'processed_at' => now()]);
    }
}
