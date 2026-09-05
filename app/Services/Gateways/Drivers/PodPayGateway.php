<?php

namespace App\Services\Gateways\Drivers;

use App\Helpers\Core;
use App\Helpers\Core as Helper;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\Contracts\PaymentGateway;
use App\Services\Gateways\PodPay\PodPayClient;
use App\Services\Gateways\PodPay\PodPayMoney;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Driver do PodPay.
 *
 * Duas pegadinhas deste provedor:
 *   1. Valores em CENTAVOS (toda conversão via PodPayMoney).
 *   2. O webhook ainda NÃO é assinado (o campo `signature` vem vazio, é roadmap
 *      deles). Por isso o crédito só acontece depois de reconsultar a transação
 *      na fonte — mesmo desenho do DigitoPay.
 */
class PodPayGateway implements PaymentGateway
{
    public function __construct(private readonly PodPayClient $client)
    {
    }

    public function key(): string
    {
        return 'podpay';
    }

    public function label(): string
    {
        return 'PodPay';
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

            $cents     = PodPayMoney::toCents($amount);
            $cpfDigits = Helper::soNumero((string) $request->input('cpf'));
            $docType   = strlen($cpfDigits) === 14 ? 'cnpj' : 'cpf';
            $name      = mb_substr(trim((string) ($user->name ?? $user->email ?? 'Cliente')), 0, 100);

            // customer.email e customer.phone são OBRIGATÓRIOS no contrato. Sem
            // este aviso, o gateway recusaria com erro de validação genérico e o
            // jogador só veria "não foi possível gerar o PIX".
            $email = trim((string) ($user->email ?? ''));
            $phone = Helper::soNumero((string) ($user->phone ?? ''));

            if ($email === '' || $phone === '') {
                GatewayLog::warning('PODPAY', 'cash-in bloqueado: jogador sem e-mail ou telefone', null, [
                    'user' => $user->id, 'tem_email' => $email !== '', 'tem_telefone' => $phone !== '',
                ]);

                return response()->json([
                    'error' => 'Complete seu cadastro com e-mail e telefone para depositar.',
                ], 400);
            }

            // Nossa chave: serve de idempotência no header e de reconciliação
            // (vai para transactions.idUnico).
            $externalId = (string) Str::uuid();

            $res = $this->client->createTransaction([
                'paymentMethod' => 'pix',
                'amount'        => $cents,
                'customer'      => [
                    'document' => ['type' => $docType, 'number' => $cpfDigits],
                    'name'     => $name,
                    'email'    => $email,
                    'phone'    => $phone,
                ],
                // A API exige ao menos um item; representamos o depósito como um.
                'items' => [[
                    'title'     => 'Depósito',
                    'unitPrice' => $cents,
                    'quantity'  => 1,
                    'tangible'  => false,
                ]],
                'postbackUrl' => url('/podpay/callback'),
            ], $externalId);

            if (! $res->successful()) {
                GatewayLog::warning('PODPAY', 'cash-in recusado', $res->status(), [
                    'body' => mb_substr((string) $res->body(), 0, 300),
                ]);

                return response()->json(['error' => 'Não foi possível gerar o PIX.'], 500);
            }

            $data          = (array) ($res->json('data') ?? $res->json() ?? []);
            $transactionId = (string) ($data['id'] ?? '');
            $qrcode        = (string) ($data['pixQrCode'] ?? '');

            if ($transactionId === '' || $qrcode === '') {
                GatewayLog::warning('PODPAY', 'resposta sem dados de PIX', null, $data);

                return response()->json(['error' => 'Gateway não retornou dados suficientes.'], 500);
            }

            $this->persist($transactionId, $externalId, $amount, $request, $qrcode, $data);

            return response()->json([
                'status'        => true,
                'idTransaction' => $transactionId,
                'qrcode'        => $qrcode,
                'qrCodeBase64'  => null,
            ]);
        } catch (\Throwable $e) {
            GatewayLog::exception('PODPAY', 'exceção no cash-in', $e);

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

        // price fica em REAIS, como todo o resto da base. Centavos só no ar.
        Transaction::create([
            'payment_id'       => $transactionId,
            'user_id'          => $user->id,
            'payment_method'   => 'pix',
            'gateway'          => $this->key(),
            'price'            => $amount,
            'currency'         => 'BRL',
            'status'           => 0,
            'idUnico'          => $externalId,
            'gateway_status'   => strtolower((string) ($gatewayResponse['status'] ?? 'pending')),
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
     * Saque.
     *
     * netPayout=true: o `amount` é o LÍQUIDO que o jogador recebe e a taxa é
     * debitada à parte da conta PodPay. É o que bate com a nossa base, onde
     * withdrawals.amount é o valor pedido pelo jogador.
     */
    public function cashOut(int $withdrawalId, ?string $pixType = null): bool
    {
        try {
            if (! $this->isConfigured()) {
                return false;
            }

            $w = Withdrawal::where('id', $withdrawalId)->where('status', 0)->first();
            if (! $w) {
                return false;
            }

            $amount = round((float) $w->amount, 2);
            if ($amount < 1) {
                return false;
            }

            $keyType = $this->mapPixKeyType((string) ($pixType ?: $w->pix_type), (string) $w->pix_key);
            if ($keyType === null) {
                GatewayLog::warning('PODPAY', 'tipo de chave PIX não suportado', null, [
                    'withdrawal' => $w->id, 'pix_type' => $pixType ?: $w->pix_type,
                ]);

                return false;
            }

            // A chave de idempotência é DETERMINÍSTICA a partir do id do saque,
            // em vez de um UUID guardado: assim um reenvio usa exatamente a
            // mesma chave (o PodPay devolve o saque já criado em vez de pagar de
            // novo) e o payment_id fica livre para guardar o id DELES — que é o
            // único identificador que o webhook de saque devolve.
            $idempotencyKey = $this->withdrawalIdempotencyKey($w->id);

            $w->status  = 9;
            $w->gateway = $this->key();
            $w->save();

            $res = $this->client->createWithdrawal([
                'method'     => 'fiat',
                'amount'     => PodPayMoney::toCents($amount),
                'pixKey'     => (string) $w->pix_key,
                'pixKeyType' => $keyType,
                'netPayout'  => true,
            ], $idempotencyKey);

            if (! $res->successful()) {
                // 409 = mesma chave em processamento. NÃO é falha: o saque já
                // está andando lá. Voltar para 0 aqui permitiria uma segunda
                // tentativa em cima de um pagamento que pode sair.
                if ($res->status() === 409) {
                    GatewayLog::warning('PODPAY', 'saque já em processamento (409)', 409, [
                        'withdrawal' => $w->id,
                    ]);

                    return false;
                }

                GatewayLog::warning('PODPAY', 'cash-out recusado', $res->status(), [
                    'withdrawal' => $w->id,
                    'body'       => mb_substr((string) $res->body(), 0, 300),
                ]);

                // A chave de idempotência é derivada do id, então voltar para 0
                // é seguro: um reprocesso manda exatamente a mesma chave.
                $w->status = 0;
                $w->save();

                return false;
            }

            // Guarda o id DELES (wd_...): o webhook de saque só devolve esse
            // identificador — não há external_id no payload. Sem isto o saque
            // nunca seria encontrado e ficaria travado em 9 para sempre.
            $providerId = (string) ($res->json('data.id') ?? '');

            if ($providerId !== '') {
                $w->payment_id = $providerId;
                $w->save();
            } else {
                GatewayLog::warning('PODPAY', 'saque aceito sem id na resposta', null, [
                    'withdrawal' => $w->id,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            GatewayLog::exception('PODPAY', 'exceção no cash-out', $e);

            // Timeout não prova que não pagou: fica em 9 e o webhook decide.
            return false;
        }
    }

    /**
     * Chave de idempotência do saque: determinística e estável entre tentativas.
     * A API exige UUID ou alfanumérico de 8 a 64 chars — daí o hash em hex.
     */
    private function withdrawalIdempotencyKey(int $withdrawalId): string
    {
        return substr(hash('sha256', 'podpay-withdrawal-' . $withdrawalId), 0, 32);
    }

    /** Tipo interno -> pixKeyType do PodPay (minúsculo). */
    private function mapPixKeyType(string $internal, string $pixKey): ?string
    {
        return match (strtolower(trim($internal))) {
            'document', 'cpf', 'cnpj' => strlen(Helper::soNumero($pixKey)) === 14 ? 'cnpj' : 'cpf',
            'email'                   => 'email',
            'phone'                   => 'phone',
            'random', 'evp'           => 'evp',
            default                   => null,
        };
    }
}
