<?php

namespace App\Services\Gateways\Drivers;

use App\Helpers\Core;
use App\Helpers\Core as Helper;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\AbilityPay\AbilityPayClient;
use App\Services\Gateways\Contracts\PaymentGateway;
use App\Services\Gateways\WithdrawalDispatchClaim;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Driver do AbilityPay.
 *
 * Particularidades deste provedor:
 *  - Valores em REAIS, e a cobrança mínima é R$ 5,00 (não R$ 1,00).
 *  - O external_id é gerado por ELES (MERCHANT_PIX_… / HPAY_PAYOUT_…), não por
 *    nós — é o identificador que o webhook devolve e que a consulta de status
 *    aceita, então é ele que guardamos em payment_id.
 *  - O webhook NÃO tem assinatura: o crédito só sai depois de reconsultar a
 *    transação na fonte (mesmo desenho do DigitoPay e do PodPay).
 */
class AbilityPayGateway implements PaymentGateway
{
    /** Mínimo do provedor com a adquirente ativa. */
    private const MIN_CHARGE = 5.00;

    public function __construct(private readonly AbilityPayClient $client)
    {
    }

    public function key(): string
    {
        return 'abilitypay';
    }

    public function label(): string
    {
        return 'AbilityPay';
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

            // Mínimo do provedor é R$ 5,00 — maior que o dos outros gateways.
            // Sem esta checagem o jogador só veria "não foi possível gerar o PIX".
            if ($amount < self::MIN_CHARGE) {
                return response()->json([
                    'error' => 'O valor mínimo de depósito neste gateway é R$ 5,00.',
                ], 400);
            }

            // Nossa referência interna: volta em metadata.reference_id e é o que
            // guardamos em idUnico para o finalizador casar.
            $referenceId = (string) Str::uuid();

            $res = $this->client->createCharge([
                'amount'       => $amount,
                'cpf'          => Helper::soNumero((string) $request->input('cpf')),
                'reference_id' => $referenceId,
                'description'  => 'Depósito',
            ]);

            if (! $res->successful()) {
                GatewayLog::warning('ABILITYPAY', 'cash-in recusado', $res->status(), [
                    'body' => mb_substr((string) $res->body(), 0, 300),
                ]);

                return response()->json(['error' => 'Não foi possível gerar o PIX.'], 500);
            }

            $data = (array) ($res->json('data') ?? $res->json() ?? []);

            // O external_id (MERCHANT_PIX_…) é DELES e é a chave de tudo: é o que
            // o webhook devolve e o que a consulta de status aceita.
            $externalId = (string) ($data['external_id'] ?? '');
            $pixCode    = (string) ($data['pix_code'] ?? '');

            if ($externalId === '' || $pixCode === '') {
                GatewayLog::warning('ABILITYPAY', 'resposta sem dados de PIX', null, $data);

                return response()->json(['error' => 'Gateway não retornou dados suficientes.'], 500);
            }

            $this->persist($externalId, $referenceId, $amount, $request, $pixCode, $data);

            return response()->json([
                'status'        => true,
                'idTransaction' => $externalId,
                'qrcode'        => $pixCode,
                'qrCodeBase64'  => null,
            ]);
        } catch (\Throwable $e) {
            GatewayLog::exception('ABILITYPAY', 'exceção no cash-in', $e);

            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    private function persist(
        string $externalId,
        string $referenceId,
        float $amount,
        Request $request,
        string $pixCode,
        array $gatewayResponse
    ): void {
        $user   = auth('api')->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        Transaction::create([
            'payment_id'       => $externalId,   // external_id DELES
            'user_id'          => $user->id,
            'payment_method'   => 'pix',
            'gateway'          => $this->key(),
            'price'            => $amount,
            'currency'         => 'BRL',
            'status'           => 0,
            'idUnico'          => $referenceId,  // nossa referência
            'gateway_status'   => strtolower((string) ($gatewayResponse['status'] ?? 'pending')),
            'pix_copia_e_cola' => $pixCode,
            'gateway_response' => json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE),
            'qr_received_at'   => now(),
            'client_ip'        => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'fbp'              => $request->input('fbp') ?? $request->cookie('_fbp'),
            'fbc'              => $request->input('fbc') ?? $request->cookie('_fbc'),
            'source_url'       => $request->headers->get('Origin') ?? $request->headers->get('Referer'),
        ]);

        Deposit::create([
            'payment_id' => $externalId,
            'user_id'    => $user->id,
            'amount'     => $amount,
            'type'       => 'pix',
            'currency'   => $wallet->currency,
            'symbol'     => $wallet->symbol,
            'status'     => 0,
        ]);

        \App\Services\BetCrm\BetCrmService::sendDeposit($user, $amount, 'dep_' . $externalId, [
            'status'   => \App\Services\BetCrm\BetCrmService::STATUS_PENDING,
            'method'   => 'pix',
            'provider' => $this->key(),
            'currency' => 'BRL',
        ]);
    }

    /**
     * Saque.
     *
     * `amount` é o LÍQUIDO que o jogador recebe — taxa sai da conta do provedor.
     * O KYC é exigido do lado DELES: conta sem KYC aprovado recebe 403.
     */
    public function cashOut(int $withdrawalId, ?string $pixType = null): bool
    {
        try {
            if (! $this->isConfigured()) {
                return false;
            }

            $w = WithdrawalDispatchClaim::claim($withdrawalId, $this->key());
            if (! $w) {
                return false;
            }

            $amount = round((float) $w->amount, 2);
            if ($amount <= 0) {
                return false;
            }

            $keyType = $this->mapPixKeyType((string) ($pixType ?: $w->pix_type), (string) $w->pix_key);

            $res = $this->client->createWithdrawal(array_filter([
                'amount'       => $amount,
                'pix_key'      => (string) $w->pix_key,
                'pix_key_type' => $keyType,
                'cpf'          => Helper::soNumero((string) $w->cpf),
                'description'  => 'Saque #' . $w->id,
            ], fn ($v) => $v !== null && $v !== ''), $this->withdrawalIdempotencyKey($w->id));

            if (! $res->successful()) {
                GatewayLog::warning('ABILITYPAY', 'cash-out recusado', $res->status(), [
                    'withdrawal' => $w->id,
                    'body'       => mb_substr((string) $res->body(), 0, 300),
                ]);

                if ($res->status() !== 409) {
                    WithdrawalDispatchClaim::releaseToPending($w->id);
                }

                return false;
            }

            $data = (array) ($res->json('data') ?? $res->json() ?? []);

            $providerId = (string) ($data['external_id'] ?? '');

            if ($providerId !== '') {
                $w->payment_id = $providerId;
                $w->save();
            } else {
                GatewayLog::warning('ABILITYPAY', 'saque aceito sem external_id na resposta', null, [
                    'withdrawal' => $w->id,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            GatewayLog::exception('ABILITYPAY', 'exceção no cash-out', $e);

            return false;
        }
    }

    /**
     * Chave de idempotência do saque — OBRIGATÓRIA neste provedor.
     *
     * Determinística a partir do id do saque.
     */
    private function withdrawalIdempotencyKey(int $withdrawalId): string
    {
        return 'wd_' . $withdrawalId . '_' . substr(hash('sha256', 'abilitypay-wd-' . $withdrawalId), 0, 16);
    }

    /** Tipo interno -> pix_key_type do AbilityPay (minúsculo). */
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
