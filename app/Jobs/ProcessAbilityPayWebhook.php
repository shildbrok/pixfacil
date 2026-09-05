<?php

namespace App\Jobs;

use App\Models\GatewayWebhookLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\AbilityPay\AbilityPayClient;
use App\Services\Payments\DepositPaymentFinalizer;
use App\Support\GatewayLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Processa um webhook do AbilityPay.
 *
 * O provedor NÃO assina o webhook (a doc oficial não menciona assinatura nem
 * header de verificação). Logo, o payload é só "a campainha": reconsultamos a
 * transação na fonte e creditamos pelo que a API diz — mesmo desenho do
 * DigitoPay e do PodPay.
 *
 * O `external_id` é o único elo entre os dois lados: é o que o webhook devolve
 * e o que a consulta de status aceita.
 */
class ProcessAbilityPayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(private readonly int $logId)
    {
    }

    public function handle(AbilityPayClient $client): void
    {
        $log = GatewayWebhookLog::find($this->logId);
        if (! $log || $log->processed_at) {
            return; // reenvio é normal: vira no-op
        }

        try {
            $payload    = (array) $log->payload;
            $event      = (string) ($payload['event'] ?? '');
            $data       = (array) ($payload['data'] ?? []);
            $externalId = (string) ($data['external_id'] ?? '');

            if ($externalId === '') {
                $this->fail($log, 'webhook sem external_id');

                return;
            }

            str_starts_with($event, 'payout.')
                ? $this->handleCashOut($log, $externalId, $event)
                : $this->handleCashIn($log, $client, $externalId, $event);

            $log->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            GatewayLog::exception('ABILITYPAY', 'exceção ao processar webhook', $e, ['log_id' => $log->id]);
            $log->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    private function handleCashIn(GatewayWebhookLog $log, AbilityPayClient $client, string $externalId, string $event): void
    {
        $local = Transaction::where('payment_id', $externalId)->first();

        if (! $local) {
            $this->fail($log, 'transação não encontrada na base');

            return;
        }

        // charge.pending só avisa que a cobrança nasceu — não move dinheiro.
        if ($event !== 'charge.approved') {
            $local->update(['gateway_status' => $event]);

            return;
        }

        // A verdade vem da API, não do payload.
        $res = $client->getTransaction($externalId);

        if (! $res->successful()) {
            $this->fail($log, 'getTransaction falhou: HTTP ' . $res->status());

            return;
        }

        $tx     = (array) ($res->json('data') ?? $res->json() ?? []);
        $status = strtolower(trim((string) ($tx['status'] ?? '')));

        if ($status !== 'approved') {
            // O webhook disse aprovado, a fonte não confirma: não credita.
            GatewayLog::warning('ABILITYPAY', 'webhook diz aprovado mas a fonte não confirma', null, [
                'external_id' => $externalId, 'status_fonte' => $status,
            ]);
            $this->fail($log, "fonte não confirma aprovação: {$status}");

            return;
        }

        // Valor da fonte x valor da nossa base. Ambos em reais.
        // Usa `amount` (bruto), não `net_amount`: o líquido já vem com a taxa do
        // provedor descontada e nunca bateria com o valor que o jogador pediu.
        $expected = (float) $local->price;
        $paid     = (float) ($tx['amount'] ?? $tx['gross_amount'] ?? 0);

        if ($paid > 0 && abs($paid - $expected) > 0.01) {
            GatewayLog::warning('ABILITYPAY', 'valor divergente — não creditado', null, [
                'external_id' => $externalId, 'esperado' => $expected, 'fonte' => $paid,
            ]);
            $this->fail($log, "valor divergente: esperado {$expected}, fonte {$paid}");

            return;
        }

        // O crédito é do finalizador: mesmo lock e mesma trava 0->1.
        DepositPaymentFinalizer::finalize($externalId, (string) $local->idUnico, null, 'abilitypay');
    }

    private function handleCashOut(GatewayWebhookLog $log, string $externalId, string $event): void
    {
        $w = Withdrawal::where('payment_id', $externalId)->first();

        if (! $w) {
            $this->fail($log, 'saque não encontrado na base');

            return;
        }

        // Já finalizado: não regride (webhook fora de ordem acontece).
        if (in_array((int) $w->status, [1, 2], true)) {
            return;
        }

        match ($event) {
            'payout.approved' => $w->update(['status' => 1]),
            'payout.failed'   => $this->refundWithdrawal($w),
            default           => $w->update(['status' => 9]),
        };
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
        GatewayLog::warning('ABILITYPAY', 'webhook não processado', null, ['motivo' => $reason]);
        $log->update(['error' => $reason, 'processed_at' => now()]);
    }
}
