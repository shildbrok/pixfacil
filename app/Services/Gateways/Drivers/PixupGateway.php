<?php

namespace App\Services\Gateways\Drivers;

use App\Helpers\Core;
use App\Helpers\Core as Helper;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\Contracts\PaymentGateway;
use App\Services\Gateways\WithdrawalDispatchClaim;
use App\Services\Gateways\Pixup\PixupClient;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Driver do PIXUP.
 *
 * Diferente do DigitoPay, aqui o webhook é assinado com HMAC de verdade — então
 * a assinatura é a defesa de entrada e não precisamos reconsultar a fonte a cada
 * evento. O crédito continua sendo do DepositPaymentFinalizer, com o mesmo lock
 * e a mesma trava 0->1 de todos os gateways.
 *
 * Valores em REAIS com 2 casas (não centavos). Mínimo R$ 1,00.
 */
class PixupGateway implements PaymentGateway
{
    public function __construct(private readonly PixupClient $client)
    {
    }

    public function key(): string
    {
        return 'pixup';
    }

    public function label(): string
    {
        return 'PIXUP';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function supportedPixTypes(): array
    {
        return ['document', 'email', 'phone', 'random'];
    }

    public function createDeposit(Request $request): JsonResponse
    {
        try {
            $setting = Core::getSetting();

            $validator = Validator::make($request->all(), [
                'amount' => ['required', 'numeric', 'min:' . $setting->min_deposit, 'max:' . $setting->max_deposit],
                'cpf'    => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            if (! $this->isConfigured()) {
                return response()->json(['error' => 'Credenciais do gateway não configuradas.'], 500);
            }

            $user   = auth('api')->user();
            $amount = round((float) $request->input('amount'), 2);

            if ($amount < 1) {
                return response()->json(['error' => 'O valor mínimo de depósito é R$ 1,00.'], 400);
            }

            $cpfDigits = Helper::soNumero((string) $request->input('cpf'));
            $name      = mb_substr(trim((string) ($user->name ?? $user->email ?? 'Cliente')), 0, 100);

            // external_id é NOSSA chave de idempotência e de reconciliação.
            // Vai para transactions.idUnico e volta no webhook.
            $externalId = (string) Str::uuid();

            $res = $this->client->cashIn([
                'amount'       => $amount,
                'currency'     => 'BRL',
                'external_id'  => $externalId,
                'postback_url' => url('/pixup/callback'),
                'payer'        => [
                    'name'     => $name,
                    'document' => $cpfDigits,
                ],
            ]);

            if (! $res->successful() || ! ($res->json('success') ?? false)) {
                GatewayLog::warning('PIXUP', 'cash-in recusado', $res->status(), [
                    'body' => mb_substr((string) $res->body(), 0, 300),
                ]);

                return response()->json(['error' => 'Não foi possível gerar o PIX.'], 500);
            }

            $data          = (array) ($res->json('data') ?? []);
            $transactionId = (string) ($data['transaction_id'] ?? '');
            $qrcode        = (string) ($data['payment_info']['qrcode'] ?? '');

            if ($transactionId === '' || $qrcode === '') {
                GatewayLog::warning('PIXUP', 'resposta sem dados de PIX', null, $data);

                return response()->json(['error' => 'Gateway não retornou dados suficientes.'], 500);
            }

            $this->persist($transactionId, $externalId, $amount, $request, $qrcode, $data);

            // Shape fixo esperado pelo front: ele desenha o QR do copia-e-cola.
            return response()->json([
                'status'        => true,
                'idTransaction' => $transactionId,
                'qrcode'        => $qrcode,
                'qrCodeBase64'  => null,
            ]);
        } catch (\Throwable $e) {
            GatewayLog::exception('PIXUP', 'exceção no cash-in', $e);

            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    private function persist(
        string $transactionId,
        string $externalId,
        float $amount,
        Request $request,
        string $qrcode,
        array $gatewayResponse
    ): void {
        $user   = auth('api')->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        Transaction::create([
            'payment_id'       => $transactionId,   // id do PIXUP
            'user_id'          => $user->id,
            'payment_method'   => 'pix',
            'gateway'          => $this->key(),
            'price'            => $amount,
            'currency'         => 'BRL',
            'status'           => 0,
            'idUnico'          => $externalId,      // nossa chave
            'gateway_status'   => (string) ($gatewayResponse['status'] ?? 'pending'),
            'pix_copia_e_cola' => $qrcode,
            'gateway_response' => json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE),
            'qr_received_at'   => now(),
            'client_ip'        => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'fbp'              => $request->input('fbp') ?? $request->cookie('_fbp'),
            'fbc'              => $request->input('fbc') ?? $request->cookie('_fbc'),
            'source_url'       => $request->headers->get('Origin') ?? $request->headers->get('Referer'),
        ]);

        Deposit::create([
            'payment_id' => $transactionId,
            'user_id'    => $user->id,
            'amount'     => $amount,
            'type'       => 'pix',
            'currency'   => $wallet->currency,
            'symbol'     => $wallet->symbol,
            'status'     => 0,
        ]);

        \App\Services\BetCrm\BetCrmService::sendDeposit($user, $amount, 'dep_' . $transactionId, [
            'status'   => \App\Services\BetCrm\BetCrmService::STATUS_PENDING,
            'method'   => 'pix',
            'provider' => $this->key(),
            'currency' => 'BRL',
        ]);
    }

    /**
     * Saque. Responde 202 (pending) e o saldo já sai da conta PIXUP na hora; a
     * liquidação vem pelo webhook cashout.confirmed / cashout.failed.
     *
     * O external_id é estável por saque (guardado em payment_id): num reenvio,
     * o PIXUP reconhece a duplicata em vez de pagar de novo.
     */
    public function cashOut(int $withdrawalId, ?string $pixType = null): bool
    {
        try {
            if (! $this->isConfigured()) {
                return false;
            }

            $w = WithdrawalDispatchClaim::claim($withdrawalId, $this->key(), fn (Withdrawal $row) => (string) Str::uuid());
            if (! $w) {
                return false;
            }

            $amount = round((float) $w->amount, 2);
            if ($amount < 1) {
                return false;
            }

            $keyType = $this->mapPixKeyType((string) ($pixType ?: $w->pix_type), (string) $w->pix_key);
            if ($keyType === null) {
                GatewayLog::warning('PIXUP', 'tipo de chave PIX não suportado', null, [
                    'withdrawal' => $w->id, 'pix_type' => $pixType ?: $w->pix_type,
                ]);

                return false;
            }

            $externalId = (string) $w->payment_id;

            $res = $this->client->cashOut([
                'external_id'  => $externalId,
                'amount'       => $amount,
                'currency'     => 'BRL',
                'key'          => (string) $w->pix_key,
                'key_type'     => $keyType,
                'name'         => (string) $w->name,
                // Obrigatório mandar aqui também: sem isto o provedor usaria o
                // default do dashboard (que ninguém configura), o webhook de
                // confirmação nunca chegaria e o saque ficaria preso em 9.
                'postback_url' => url('/pixup/callback'),
            ]);

            // 202 = aceito (assíncrono). Só 2xx conta como aceito.
            if (! $res->successful()) {
                GatewayLog::warning('PIXUP', 'cash-out recusado', $res->status(), [
                    'withdrawal' => $w->id,
                    'body'       => mb_substr((string) $res->body(), 0, 300),
                ]);

                // Volta para pendente PRESERVANDO o external_id: gerar outro num
                // reprocesso arriscaria pagamento duplo.
                WithdrawalDispatchClaim::releaseToPending($w->id);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            GatewayLog::exception('PIXUP', 'exceção no cash-out', $e);

            // Timeout não significa que não pagou: deixa em processamento (9)
            // e o webhook decide.
            return false;
        }
    }

    /** Tipo interno -> key_type do PIXUP (minúsculo). */
    private function mapPixKeyType(string $internal, string $pixKey): ?string
    {
        return match (strtolower(trim($internal))) {
            'document', 'cpf', 'cnpj' => strlen(Helper::soNumero($pixKey)) === 14 ? 'cnpj' : 'cpf',
            'email'                   => 'email',
            'phone'                   => 'phone',
            'random', 'evp'           => 'random',
            default                   => null,
        };
    }
}
