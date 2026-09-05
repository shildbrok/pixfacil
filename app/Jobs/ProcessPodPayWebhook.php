<?php

namespace App\Jobs;

use App\Models\GatewayWebhookLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\PodPay\PodPayClient;
use App\Services\Gateways\PodPay\PodPayMoney;
use App\Services\Payments\DepositPaymentFinalizer;
use App\Support\GatewayLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Processa um webhook do PodPay.
 *
 * O PodPay documenta uma assinatura HMAC, mas ela AINDA VEM VAZIA (é roadmap
 * deles). Então, na prática, o webhook é não-assinado: o payload é só "a
 * campainha" e NUNCA credita sozinho. Reconsultamos a transação na fonte e
 * creditamos pelo que a API diz — mesmo desenho do DigitoPay.
 *
 * Quando eles ativarem a assinatura, dá para validar antes e manter a
 * reconsulta como reforço.
 */
class ProcessPodPayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(private readonly int $logId)
    {
    }

    public function handle(PodPayClient $client): void
    {
        $log = GatewayWebhookLog::find($this->logId);
        if (! $log || $log->processed_at) {
            return; // reenvio de webhook é normal: vira no-op
        }

        try {
            $payload = (array) $log->payload;
            $event   = (string) ($payload['event'] ?? '');
            $data    = (array) ($payload['data'] ?? []);
            $id      = (string) ($data['id'] ?? '');

            if ($id === '') {
                $this->fail($log, 'webhook sem id');

                return;
            }

            str_starts_with($event, 'withdrawal.')
                ? $this->handleCashOut($log, $id, $event)
                : $this->handleCashIn($log, $client, $id);

            $log->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            GatewayLog::exception('PODPAY', 'exceção ao processar webhook', $e, ['log_id' => $log->id]);
            $log->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    private function handleCashIn(GatewayWebhookLog $log, PodPayClient $client, string $id): void
    {
        // A verdade vem da API, não do payload.
        $res = $client->getTransaction($id);

        if (! $res->successful()) {
            $this->fail($log, 'getTransaction falhou: HTTP ' . $res->status());

            return;
        }

        $tx = (array) ($res->json('data') ?? $res->json() ?? []);

        // O status vem MINÚSCULO no REST e MAIÚSCULO no webhook — normalizamos
        // sempre, e aqui lemos do REST de qualquer forma.
        $status = strtolower(trim((string) ($tx['status'] ?? '')));

        $local = Transaction::where('payment_id', $id)->first();
        if (! $local) {
            $this->fail($log, 'transação não encontrada na base');

            return;
        }

        // Atenção ao 'canceled' com UM L: é assim no enum oficial. Com dois,
        // a comparação nunca casaria e a transação ficaria pendente para sempre.
        if (in_array($status, ['failed', 'canceled', 'refunded', 'chargeback'], true)) {
            if ((int) $local->status === 0) {
                $local->update(['status' => 2, 'gateway_status' => $status]);
            }

            return;
        }

        if ($status !== 'paid') {
            $local->update(['gateway_status' => $status]);

            return;
        }

        // Valor: a fonte fala CENTAVOS, a base fala reais.
        $expected = (float) $local->price;
        $paid     = PodPayMoney::toReais($tx['amount'] ?? 0);

        if ($paid > 0 && abs($paid - $expected) > 0.01) {
            GatewayLog::warning('PODPAY', 'valor divergente — não creditado', null, [
                'transaction' => $id, 'esperado' => $expected, 'fonte' => $paid,
            ]);
            $this->fail($log, "valor divergente: esperado {$expected}, fonte {$paid}");

            return;
        }

        // O crédito é do finalizador: mesmo lock e mesma trava 0->1.
        DepositPaymentFinalizer::finalize($id, (string) $local->idUnico, null, 'podpay');
    }

    private function handleCashOut(GatewayWebhookLog $log, string $id, string $event): void
    {
        // O webhook de saque do PodPay devolve APENAS o id dele (wd_...) — não
        // existe external_id neste payload. Por isso o driver grava esse id em
        // payment_id logo após criar o saque; é o único elo entre os dois lados.
        $w = Withdrawal::where('payment_id', $id)->first();

        if (! $w) {
            $this->fail($log, 'saque não encontrado na base');

            return;
        }

        // Já finalizado: não regride (webhook fora de ordem acontece).
        if (in_array((int) $w->status, [1, 2], true)) {
            return;
        }

        match ($event) {
            'withdrawal.completed' => $w->update(['status' => 1]),
            'withdrawal.failed', 'withdrawal.canceled' => $this->refundWithdrawal($w),
            default => $w->update(['status' => 9]),
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
        GatewayLog::warning('PODPAY', 'webhook não processado', null, ['motivo' => $reason]);
        $log->update(['error' => $reason, 'processed_at' => now()]);
    }
}
