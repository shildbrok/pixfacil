<?php

namespace App\Jobs;

use App\Models\GatewayWebhookLog;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Services\Gateways\DigitoPay\DigitoPayClient;
use App\Services\Gateways\DigitoPay\DigitoPayStatus;
use App\Services\Payments\DepositPaymentFinalizer;
use App\Support\GatewayLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processa um webhook do DigitoPay.
 *
 * REGRA DE OURO: o payload do webhook é só "a campainha tocando". Ele não tem
 * assinatura, então sozinho não prova nada e NUNCA credita saldo. Aqui a gente
 * reconsulta getTransaction na fonte e só credita com base no que a API diz.
 *
 * Roda via dispatchAfterResponse (não há worker de fila nesta instalação): o
 * webhook responde 200 na hora e o processamento acontece logo depois, no mesmo
 * processo.
 */
class ProcessDigitoPayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(private readonly int $logId)
    {
    }

    public function handle(DigitoPayClient $client): void
    {
        $log = GatewayWebhookLog::find($this->logId);
        if (! $log || $log->processed_at) {
            return; // já processado: webhook reenviado é normal, vira no-op
        }

        try {
            $payload = (array) $log->payload;

            // RO-1: a verdade vem da API, não do payload.
            $res = $client->getTransaction(
                id: $payload['id'] ?? null,
                idempotencyKey: $payload['idempotencyKey'] ?? null,
            );

            if (! $res->successful()) {
                $this->fail($log, 'getTransaction falhou: HTTP ' . $res->status());

                return;
            }

            $tx     = (array) $res->json();
            $status = DigitoPayStatus::parse($tx['status'] ?? null);

            if ($status === null) {
                // Status fora do enum: não inventa comportamento, manda revisar.
                $this->fail($log, 'status desconhecido: ' . (string) ($tx['status'] ?? 'null'));

                return;
            }

            $log->channel === 'cashout'
                ? $this->handleCashOut($log, $tx, $status)
                : $this->handleCashIn($log, $tx, $status);

            $log->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            GatewayLog::exception('DIGITOPAY', 'exceção ao processar webhook', $e, ['log_id' => $log->id]);
            $log->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    /**
     * Depósito. Só credita em REALIZADO, e o crédito em si é o
     * DepositPaymentFinalizer — o MESMO caminho do GeraPix, com lock e trava de
     * idempotência (status 0 -> 1). Webhook duplicado não credita duas vezes.
     */
    private function handleCashIn(GatewayWebhookLog $log, array $tx, DigitoPayStatus $status): void
    {
        $providerId     = (string) ($tx['id'] ?? '');
        $idempotencyKey = (string) ($tx['idempotencyKey'] ?? '');

        $local = Transaction::where('payment_id', $providerId)
            ->where('idUnico', $idempotencyKey)
            ->first();

        if (! $local) {
            $this->fail($log, 'transação não encontrada na base');

            return;
        }

        if ($status === DigitoPayStatus::Cancelado) {
            if ((int) $local->status === 0) {
                $local->update(['status' => 2, 'gateway_status' => $status->value]);
            }

            return;
        }

        if ($status !== DigitoPayStatus::Realizado) {
            $local->update(['gateway_status' => $status->value]);

            return;
        }

        // RO-2: o valor é o da NOSSA base, e tem que bater com o da fonte.
        $expected = (float) $local->price;
        $paid     = (float) ($tx['requestedValue'] ?? 0);

        if ($paid > 0 && abs($paid - $expected) > 0.01) {
            GatewayLog::warning('DIGITOPAY', 'valor divergente — não creditado', null, [
                'transaction' => $providerId, 'esperado' => $expected, 'fonte' => $paid,
            ]);
            $this->fail($log, "valor divergente: esperado {$expected}, fonte {$paid}");

            return;
        }

        // O finalizador tem o lock e a trava 0->1. É ele quem credita.
        DepositPaymentFinalizer::finalize($providerId, $idempotencyKey, null, 'digitopay');
    }

    /**
     * Saque. Espelha os status do provedor no nosso registro.
     * 0=Pendente, 1=Aprovado, 2=Cancelado, 9=Em processamento.
     */
    private function handleCashOut(GatewayWebhookLog $log, array $tx, DigitoPayStatus $status): void
    {
        $idempotencyKey = (string) ($tx['idempotencyKey'] ?? '');

        $w = Withdrawal::where('payment_id', $idempotencyKey)->first();
        if (! $w) {
            $this->fail($log, 'saque não encontrado na base');

            return;
        }

        // Já finalizado: não regride status (webhook fora de ordem é possível).
        if (in_array((int) $w->status, [1, 2], true)) {
            return;
        }

        match ($status) {
            DigitoPayStatus::Realizado => $w->update(['status' => 1]),
            DigitoPayStatus::Cancelado, DigitoPayStatus::Erro => $this->refundWithdrawal($w),
            // PENDENTE/ANALISE/EM PROCESSAMENTO: segue em processamento (9).
            default => $w->update(['status' => 9]),
        };
    }

    /**
     * Saque recusado: devolve o valor ao saldo sacável e cancela.
     * Reaproveita o caminho que o GeraPix já usa para estorno.
     */
    private function refundWithdrawal(Withdrawal $w): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($w) {
            $fresh = Withdrawal::where('id', $w->id)->lockForUpdate()->first();

            // Só estorna uma vez: se já saiu de 0/9, alguém já tratou.
            if (! $fresh || in_array((int) $fresh->status, [1, 2], true)) {
                return;
            }

            $wallet = \App\Models\Wallet::where('user_id', $fresh->user_id)->lockForUpdate()->first();
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
        GatewayLog::warning('DIGITOPAY', 'webhook não processado', null, ['motivo' => $reason]);
        $log->update(['error' => $reason, 'processed_at' => now()]);
    }
}
