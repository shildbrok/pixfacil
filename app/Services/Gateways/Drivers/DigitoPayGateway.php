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
use App\Services\Gateways\DigitoPay\DigitoPayClient;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Driver do DigitoPay (PIX Brasil).
 *
 * O que este driver NÃO faz: creditar saldo. Ele cria a cobrança e persiste a
 * transação com status 0; quem credita é o DepositPaymentFinalizer, depois do
 * webhook ser CONFIRMADO na fonte (ver ProcessDigitoPayWebhook). O webhook do
 * DigitoPay não tem assinatura — confiar nele para creditar seria falha grave.
 */
class DigitoPayGateway implements PaymentGateway
{
    public function __construct(private readonly DigitoPayClient $client)
    {
    }

    public function key(): string
    {
        return 'digitopay';
    }

    public function label(): string
    {
        return 'DigitoPay';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** O DigitoPay paga saque para qualquer tipo de chave PIX. */
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
            $amount = (float) $request->input('amount');

            // Valor mínimo do próprio provedor: R$ 1,00 (cash in e cash out).
            if ($amount < 1) {
                return response()->json(['error' => 'O valor mínimo de depósito é R$ 1,00.'], 400);
            }

            $cpfDigits = Helper::soNumero((string) $request->input('cpf'));

            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $name = trim((string) ($user->email ?? 'Cliente'));
            }
            $name = mb_substr($name, 0, 100);

            // RO-4: a idempotencyKey é NOSSA e é a chave de reconciliação com o
            // provedor. Vai para transactions.idUnico e volta no webhook.
            $idempotencyKey = (string) Str::uuid();

            $res = $this->client->deposit([
                'dueDate'        => now()->addMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z'),
                'paymentOptions' => ['PIX'],
                'person'         => ['cpf' => $cpfDigits, 'name' => $name],
                'value'          => round($amount, 2),
                'callbackUrl'    => url('/digitopay/callback/cashin'),
                'idempotencyKey' => $idempotencyKey,
            ]);

            if (! $res->successful()) {
                GatewayLog::warning('DIGITOPAY', 'cash-in recusado', $res->status(), [
                    'body' => mb_substr((string) $res->body(), 0, 300),
                ]);

                return response()->json(['error' => 'Não foi possível gerar o PIX.'], 500);
            }

            $resp         = (array) $res->json();
            $providerId   = (string) ($resp['id'] ?? '');
            $qrcode       = (string) ($resp['pixCopiaECola'] ?? '');
            $qrCodeBase64 = $resp['qrCodeBase64'] ?? null;

            if ($providerId === '' || $qrcode === '') {
                GatewayLog::warning('DIGITOPAY', 'resposta sem dados de PIX', null, $resp);

                return response()->json(['error' => 'Gateway não retornou dados suficientes.'], 500);
            }

            $this->persist($providerId, $idempotencyKey, $amount, $request, $qrcode, $qrCodeBase64, $resp);

            // Shape fixo esperado pelo front (DepositPage.vue / DepositWidget.vue):
            // ele desenha o QR a partir do copia-e-cola.
            return response()->json([
                'status'        => true,
                'idTransaction' => $providerId,
                'qrcode'        => $qrcode,
                'qrCodeBase64'  => $qrCodeBase64,
            ]);
        } catch (\Throwable $e) {
            GatewayLog::exception('DIGITOPAY', 'exceção no cash-in', $e);

            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * Persiste Transaction + Deposit com status 0 — a janela que o
     * DepositPaymentFinalizer fecha (0 -> 1) ao creditar. Espelha o GeraPix,
     * inclusive a captura de atribuição da Meta.
     */
    private function persist(
        string $providerId,
        string $idempotencyKey,
        float $amount,
        Request $request,
        string $pixCopiaECola,
        ?string $qrCodeBase64,
        array $gatewayResponse
    ): void {
        $user   = auth('api')->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        Transaction::create([
            'payment_id'       => $providerId,      // id do DigitoPay
            'user_id'          => $user->id,
            'payment_method'   => 'pix',
            'gateway'          => $this->key(),
            'price'            => $amount,
            'currency'         => 'BRL',
            'status'           => 0,
            'idUnico'          => $idempotencyKey,  // nossa chave (RO-4)
            'gateway_status'   => (string) ($gatewayResponse['status'] ?? 'PENDENTE'),
            'pix_copia_e_cola' => $pixCopiaECola,
            'qr_code_base64'   => $qrCodeBase64,
            'gateway_response' => json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE),
            'qr_received_at'   => now(),
            'client_ip'        => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'fbp'              => $request->input('fbp') ?? $request->cookie('_fbp'),
            'fbc'              => $request->input('fbc') ?? $request->cookie('_fbc'),
            'source_url'       => $request->headers->get('Origin') ?? $request->headers->get('Referer'),
        ]);

        Deposit::create([
            'payment_id' => $providerId,
            'user_id'    => $user->id,
            'amount'     => $amount,
            'type'       => 'pix',
            'currency'   => $wallet->currency,
            'symbol'     => $wallet->symbol,
            'status'     => 0,
        ]);

        // Depósito 'pending' no CRM; a virada para 'paid' sai do finalizador.
        \App\Services\BetCrm\BetCrmService::sendDeposit($user, $amount, 'dep_' . $providerId, [
            'status'   => \App\Services\BetCrm\BetCrmService::STATUS_PENDING,
            'method'   => 'pix',
            'provider' => $this->key(),
            'currency' => 'BRL',
        ]);
    }

    /**
     * Envia o PIX de saque.
     *
     * RO-8: person.cpf tem que SER o CPF do TITULAR da chave; se divergir, o
     * DigitoPay cancela a transação.
     * RO-6: reenvio reutiliza a MESMA idempotencyKey (guardada em payment_id),
     * senão um reprocesso após timeout pagaria duas vezes.
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

            $amount = (float) $w->amount;
            if ($amount < 1) {
                return false;
            }

            $type = $this->mapPixKeyType((string) ($pixType ?: $w->pix_type), (string) $w->pix_key);
            if ($type === null) {
                GatewayLog::warning('DIGITOPAY', 'tipo de chave PIX não suportado', null, [
                    'withdrawal' => $w->id, 'pix_type' => $pixType ?: $w->pix_type,
                ]);

                return false;
            }

            // Chave estável por saque: reusa a de uma tentativa anterior. Gerar
            // uma nova aqui faria o provedor tratar como saque novo -> pagamento duplo.
            $idempotencyKey = (string) $w->payment_id;

            $res = $this->client->withdraw([
                'paymentOptions' => ['PIX'],
                'person'         => [
                    'pixKeyTypes' => $type,
                    'pixKey'      => (string) $w->pix_key,
                    'name'        => (string) $w->name,
                    'cpf'         => Helper::soNumero((string) $w->cpf),
                ],
                'value'          => round($amount, 2),
                'callbackUrl'    => url('/digitopay/callback/cashout'),
                'idempotencyKey' => $idempotencyKey,
            ]);

            if (! $res->successful() || ! ($res->json('success') ?? false)) {
                GatewayLog::warning('DIGITOPAY', 'cash-out recusado', $res->status(), [
                    'withdrawal' => $w->id,
                    'body'       => mb_substr((string) $res->body(), 0, 300),
                ]);

                // Volta para pendente PRESERVANDO o payment_id: zerar aqui faria
                // o reprocesso gerar outra chave e arriscar pagamento duplo.
                WithdrawalDispatchClaim::releaseToPending($w->id);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            GatewayLog::exception('DIGITOPAY', 'exceção no cash-out', $e);

            // Timeout NÃO significa que não pagou — deixa em processamento (9)
            // e o webhook/reconciliação decide. Voltar para 0 aqui permitiria
            // uma segunda tentativa em cima de um PIX que talvez tenha saído.
            return false;
        }
    }

    /**
     * Tipo interno -> `pixKeyTypes` do DigitoPay (SEMPRE maiúsculo).
     * O tipo do documento sai da própria chave do saque (11 dígitos = CPF,
     * 14 = CNPJ) — não dá para ler de um request: o admin aprova saque fora
     * de qualquer requisição do jogador.
     */
    private function mapPixKeyType(string $internal, string $pixKey): ?string
    {
        return match (strtolower(trim($internal))) {
            'document', 'cpf', 'cnpj' => strlen(Helper::soNumero($pixKey)) === 14 ? 'CNPJ' : 'CPF',
            'email'                   => 'EMAIL',
            'phone'                   => 'PHONE',
            'random', 'evp'           => 'EVP',
            default                   => null,
        };
    }
}
