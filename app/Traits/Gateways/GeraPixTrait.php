<?php



namespace App\Traits\Gateways;

use App\Helpers\Core;
use App\Helpers\Core as Helper;
use App\Models\AffiliateWithdraw;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Payments\DepositPaymentFinalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


trait GeraPixTrait
{
    protected static string $uriGeraPix = '';
    protected static string $secretTokenGeraPix = '';

    private static function generateCredentialsGeraPix(): void
    {
        $gateway = Gateway::first();
        if (! $gateway) {
            return;
        }

        self::$uriGeraPix         = (string) ($gateway->gerapix_uri ?? '');
        self::$secretTokenGeraPix = (string) ($gateway->gerapix_secret_token ?? '');

        if (self::$uriGeraPix === '') {
            self::$uriGeraPix = 'https://api.gerapix.digital';
        }

        self::$uriGeraPix = rtrim(trim(self::$uriGeraPix), '/');
        self::$secretTokenGeraPix = trim(self::$secretTokenGeraPix);
    }

    private static function verifyWebhookGeraPix(Request $request): bool
    {
        if (self::$secretTokenGeraPix === '') {
            \App\Support\GatewayLog::warning('GERAPIX', 'webhook rejeitado', 401, ['message' => 'token do gateway não configurado']);
            return false;
        }

        $auth = (string) ($request->header('Authorization') ?? '');
        $auth = trim($auth);

        if ($auth === '') {
            return false;
        }

        $token = $auth;
        if (stripos($auth, 'bearer ') === 0) {
            $token = trim(substr($auth, 7));
        }

        return hash_equals(self::$secretTokenGeraPix, $token);
    }


    public function requestQrcodeGeraPix(Request $request)
    {
        try {
            $setting = Core::getSetting();

            $rules = [
                'amount' => ['required', 'numeric', 'min:' . $setting->min_deposit, 'max:' . $setting->max_deposit],
                'cpf'    => ['required', 'string', 'max:255'],
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            self::generateCredentialsGeraPix();
            if (self::$secretTokenGeraPix === '') {
                return response()->json(['error' => 'Token do gateway não configurado.'], 500);
            }

            $user = auth('api')->user();
            $cpfDigits = Helper::soNumero((string) $request->input('cpf'));
            $docTipo = (strlen($cpfDigits) === 14) ? 'cnpj' : 'cpf';

            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $name = trim((string) ($user->email ?? 'Cliente'));
            }

            if (! str_contains($name, ' ')) {
                $name .= ' Cliente';
            }

            $name = mb_substr($name, 0, 100);

            $email = trim((string) ($user->email ?? ''));
            if ($email === '') {
                return response()->json(['error' => 'E-mail do usuário é obrigatório para este gateway.'], 400);
            }

            $email = mb_substr($email, 0, 100);

            $externalId = (string) Str::uuid();
            $callbackUrl = url('/gerapix/callback');

            $amount = (float) $request->input('amount');
            $valor = number_format($amount, 2, '.', '');

            $payload = [
                'valor'              => $valor,
                'nome'               => $name,
                'email'              => $email,
                'doc_tipo'           => $docTipo,
                'doc_numero'         => $cpfDigits,
                'callback_url'       => $callbackUrl,
                'external_reference' => $externalId,
            ];

            $response = Http::withOptions(['force_ip_resolve' => 'v4'])->asJson()
                ->acceptJson()
                ->withToken(self::$secretTokenGeraPix)
                ->timeout(25)
                ->retry(2, 250)
                ->post(self::$uriGeraPix . '/v1/pix/qrcodes/', $payload);

            if (! $response->successful()) {
                Log::warning('[GERAPIX] cash-in failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                return response()->json(['error' => 'Falha ao gerar PIX.'], 500);
            }

            $resp = $response->json();

            $transactionId = (string) ($resp['id_transacao'] ?? '');
            $qrcode        = (string) ($resp['qr_code'] ?? '');
            $qrCodeBase64  = (string) ($resp['qr_code_base64'] ?? '');
            $gatewayStatus = (string) ($resp['status'] ?? 'pendente');

            if ($transactionId === '' || $qrcode === '') {
                \App\Support\GatewayLog::warning('GERAPIX', 'resposta sem dados de PIX', null, $resp);
                return response()->json(['error' => 'Gateway não retornou dados suficientes.'], 500);
            }

            self::generateTransactionGeraPix(
                transactionId: $transactionId,
                amount: $amount,
                externalId: $externalId,
                request: $request,
                pixCopiaECola: $qrcode,
                qrCodeBase64: $qrCodeBase64,
                gatewayStatus: $gatewayStatus,
                gatewayResponse: $resp
            );

            self::generateDepositGeraPix($transactionId, $amount);

            return response()->json([
                'status'        => true,
                'idTransaction' => $transactionId,
                'qrcode'        => $qrcode,
                'qrCodeBase64'  => $qrCodeBase64,
            ]);
        } catch (\Throwable $e) {
            \App\Support\GatewayLog::exception('GERAPIX', 'exceção no cash-in', $e);
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }


    public function webhookGeraPix(Request $request)
    {
        self::generateCredentialsGeraPix();

        if (! self::verifyWebhookGeraPix($request)) {
            return response()->json(['error' => 'UNAUTHORIZED'], 401);
        }

        $idTransacao = (string) ($request->input('id_transacao') ?? '');
        $status      = (string) ($request->input('status') ?? '');
        $externalRef = (string) ($request->input('external_reference') ?? '');

        if ($idTransacao === '' || $status === '') {
            return response()->json(['error' => 'BAD_REQUEST'], 400);
        }

        $statusNorm = mb_strtolower(trim($status));

        self::updateTransactionGatewayMetaGeraPix(
            transactionId: $idTransacao,
            gatewayStatus: $statusNorm,
            gatewayResponse: $request->all()
        );

        $tx = Transaction::where('payment_id', $idTransacao)->first();
        if ($tx) {
            if (self::isApprovedStatus($statusNorm)) {
                $ok = DepositPaymentFinalizer::finalize(
                    $idTransacao,
                    $externalRef ?: (string) ($tx->idUnico ?? ''),
                    $request,
                    'gerapix'
                );

                return response()->json([], $ok ? 200 : 500);
            }

            if (self::isCancelledStatus($statusNorm)) {
                self::cancelDepositGeraPix(
                    transactionId: $idTransacao,
                    externalId: $externalRef ?: (string) ($tx->idUnico ?? ''),
                    gatewayStatus: $statusNorm,
                    gatewayResponse: $request->all()
                );

                return response()->json([], 200);
            }

            return response()->json([], 200);
        }

        $ok = self::handleCashOutStatusGeraPix($idTransacao, $externalRef, $statusNorm, $request->all());
        return response()->json([], $ok ? 200 : 500);
    }


    public function pixCashOutGeraPix($id, $tipo)
    {
        try {
            self::generateCredentialsGeraPix();

            if (self::$secretTokenGeraPix === '') {
                return false;
            }

            $withdrawal = ($tipo === 'afiliado')
                ? AffiliateWithdraw::findOrFail($id)
                : Withdrawal::findOrFail($id);

            $pixType = (string) ($withdrawal->pix_type ?? '');
            if ($pixType !== 'document') {
                Log::warning('[GERAPIX] cash-out: gateway aceita apenas pix_type=document (CPF/CNPJ)', [
                    'withdrawal_id' => $withdrawal->id,
                    'pix_type'      => $pixType,
                ]);

                return false;
            }

            $externalRef = null;

            DB::transaction(function () use ($withdrawal, $tipo, &$externalRef) {
                $w = ($tipo === 'afiliado')
                    ? AffiliateWithdraw::query()->lockForUpdate()->find($withdrawal->id)
                    : Withdrawal::query()->lockForUpdate()->find($withdrawal->id);

                if (! $w || (int) $w->status !== 0) {
                    throw new \RuntimeException('Saque não está pendente.');
                }

                // external_reference ESTÁVEL por saque: reusa o de uma tentativa anterior
                // se já existir. É a chave de idempotência do GeraPix (ERR007 se duplicado),
                // então um reenvio (reprocesso após timeout/perda de resposta) usa o MESMO
                // ref e o gateway recusa o duplicado, em vez de emitir um segundo pagamento.
                $externalRef = $w->payment_id ?: (string) Str::uuid();
                $w->status = 9;
                $w->payment_id = $externalRef;
                $w->save();
            });

            $callbackUrl = url('/gerapix/callback');

            $docNumero = Helper::soNumero((string) ($withdrawal->pix_key ?? ''));
            $docTipo = (strlen($docNumero) === 14) ? 'cnpj' : 'cpf';

            $name = trim((string) ($withdrawal->name ?? ''));
            if ($name === '' || ! str_contains($name, ' ')) {
                $name = trim($name ?: 'Cliente') . ' Cliente';
            }

            $name = mb_substr($name, 0, 100);

            $amount = (float) ($withdrawal->amount ?? 0);
            $valor = number_format($amount, 2, '.', '');

            $payload = [
                'valor'              => $valor,
                'nome'               => $name,
                'doc_tipo'           => $docTipo,
                'doc_numero'         => $docNumero,
                'callback_url'       => $callbackUrl,
                'external_reference' => $externalRef,
            ];

            $response = Http::withOptions(['force_ip_resolve' => 'v4'])->asJson()
                ->acceptJson()
                ->withToken(self::$secretTokenGeraPix)
                ->timeout(25)
                ->retry(2, 250)
                ->post(self::$uriGeraPix . '/v1/pix/payments/', $payload);

            if (! $response->successful()) {
                Log::warning('[GERAPIX] cash-out failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                self::revertWithdrawalToPendingGeraPix($withdrawal->id, $tipo);
                return false;
            }

            $resp = $response->json();

            $gatewayId = (string) ($resp['id_transacao'] ?? '');
            $gatewayStatus = mb_strtolower(trim((string) ($resp['status'] ?? 'pendente')));

            if ($gatewayId === '') {
                \App\Support\GatewayLog::warning('GERAPIX', 'resposta inválida no saque', null, $resp, [
                    'withdrawal_id' => $withdrawal->id,
                    'tipo' => $tipo,
                ]);
                self::revertWithdrawalToPendingGeraPix($withdrawal->id, $tipo);
                return false;
            }

            DB::transaction(function () use ($withdrawal, $tipo, $gatewayId) {
                $w = ($tipo === 'afiliado')
                    ? AffiliateWithdraw::query()->lockForUpdate()->find($withdrawal->id)
                    : Withdrawal::query()->lockForUpdate()->find($withdrawal->id);

                if (! $w) {
                    return;
                }

                $w->payment_id = $gatewayId;
                $w->save();
            });

            self::updateTransactionGatewayMetaGeraPix(
                transactionId: $gatewayId,
                gatewayStatus: $gatewayStatus,
                gatewayResponse: $resp
            );

            return true;
        } catch (\Throwable $e) {
            \App\Support\GatewayLog::exception('GERAPIX', 'exceção no cash-out', $e, [
                'withdrawal_id' => $id,
                'tipo'          => $tipo,
            ]);

            try {
                self::revertWithdrawalToPendingGeraPix((int) $id, (string) $tipo);
            } catch (\Throwable $ignored) {
            }

            return false;
        }
    }



    private static function isApprovedStatus(string $statusNorm): bool
    {
        return in_array($statusNorm, ['aprovado', 'pago', 'confirmado', 'concluido', 'concluído'], true)
            || str_contains($statusNorm, 'aprov');
    }

    private static function isCancelledStatus(string $statusNorm): bool
    {
        $bad = ['cancelado', 'canceled', 'cancelled', 'expirado', 'expired', 'falhou', 'failed', 'recusado', 'refused', 'rejeitado', 'rejected'];

        foreach ($bad as $b) {
            if ($statusNorm === $b || str_contains($statusNorm, $b)) {
                return true;
            }
        }

        return false;
    }

    private static function generateDepositGeraPix(string $transactionId, float $amount): void
    {
        $userId = auth('api')->user()->id;
        $wallet = Wallet::where('user_id', $userId)->first();

        Deposit::create([
            'payment_id' => $transactionId,
            'user_id'    => $userId,
            'amount'     => $amount,
            'type'       => 'pix',
            'currency'   => $wallet->currency,
            'symbol'     => $wallet->symbol,
            'status'     => 0,
        ]);
    }

    private static function generateTransactionGeraPix(
        string $transactionId,
        float $amount,
        string $externalId,
        ?Request $request = null,
        ?string $pixCopiaECola = null,
        ?string $qrCodeBase64 = null,
        ?string $gatewayStatus = null,
        ?array $gatewayResponse = null
    ): void {
        $user = auth('api')->user();

        $clientIp  = $request ? $request->ip() : null;
        $userAgent = $request ? $request->userAgent() : null;
        $sourceUrl = $request ? ($request->headers->get('Origin') ?? $request->headers->get('Referer')) : null;
        $fbp       = $request ? ($request->input('fbp') ?? $request->cookie('_fbp')) : null;
        $fbc       = $request ? ($request->input('fbc') ?? $request->cookie('_fbc')) : null;

        Transaction::create([
            'payment_id'       => $transactionId,
            'user_id'          => $user->id,
            'payment_method'   => 'pix',
            'price'            => $amount,
            'currency'         => 'BRL',
            'status'           => 0,
            'idUnico'          => $externalId,
            'gateway_status'   => self::normalizeGatewayStatusGeraPix($gatewayStatus),
            'pix_copia_e_cola' => $pixCopiaECola,
            'qr_code_base64'   => $qrCodeBase64,
            'gateway_response' => self::encodeGatewayResponseGeraPix($gatewayResponse),
            'qr_received_at'   => $pixCopiaECola ? now() : null,
            'client_ip'        => $clientIp,
            'user_agent'       => $userAgent,
            'fbp'              => $fbp,
            'fbc'              => $fbc,
            'source_url'       => $sourceUrl,
        ]);

        // PIX gerado = depósito 'pending' no BetCRM. A confirmação reenvia a
        // MESMA chave com 'paid', e é essa virada que conta a venda. Sem este
        // primeiro envio, o CRM só vê o depósito quando (e se) ele for pago.
        if ($user) {
            \App\Services\BetCrm\BetCrmService::sendDeposit($user, $amount, 'dep_' . $transactionId, [
                'status'   => \App\Services\BetCrm\BetCrmService::STATUS_PENDING,
                'method'   => 'pix',
                'provider' => 'gerapix',
                'currency' => 'BRL',
            ]);
        }
    }

    private static function cancelDepositGeraPix(
        string $transactionId,
        string $externalId,
        ?string $gatewayStatus = null,
        ?array $gatewayResponse = null
    ): void {
        DB::transaction(function () use ($transactionId, $externalId, $gatewayStatus, $gatewayResponse) {
            $tx = Transaction::where('payment_id', $transactionId)
                ->where('idUnico', $externalId)
                ->whereIn('status', [0, 9])
                ->lockForUpdate()
                ->first();

            if ($tx) {
                $tx->status = 2;

                if ($gatewayStatus !== null) {
                    $tx->gateway_status = self::normalizeGatewayStatusGeraPix($gatewayStatus);
                }

                if ($gatewayResponse !== null) {
                    $tx->gateway_response = self::encodeGatewayResponseGeraPix($gatewayResponse);
                }

                $tx->save();
            }

            $dep = Deposit::where('payment_id', $transactionId)
                ->whereIn('status', [0, 9])
                ->lockForUpdate()
                ->first();

            if ($dep) {
                $dep->status = 2;
                $dep->save();
            }
        });
    }

    private static function revertWithdrawalToPendingGeraPix(int $id, string $tipo): void
    {
        DB::transaction(function () use ($id, $tipo) {
            $w = ($tipo === 'afiliado')
                ? AffiliateWithdraw::query()->lockForUpdate()->find($id)
                : Withdrawal::query()->lockForUpdate()->find($id);

            if ($w && (int) $w->status === 9) {
                $w->status = 0;
                // payment_id (external_reference) PRESERVADO de propósito: é a chave de
                // idempotência do gateway. Se o pagamento tiver sido aceito antes de a
                // resposta chegar, o reenvio com o mesmo ref cai em ERR007 e não paga de
                // novo. Zerar aqui faria o reprocesso gerar um novo ref => pagamento duplo.
                $w->save();
            }
        });
    }

    private static function handleCashOutStatusGeraPix(
        string $gatewayId,
        string $externalRef,
        string $statusNorm,
        array $payload = []
    ): bool {
        try {
            return DB::transaction(function () use ($gatewayId, $externalRef, $statusNorm, $payload) {
                $withdrawal = Withdrawal::where('payment_id', $gatewayId)->lockForUpdate()->first();
                $isAffiliate = false;

                if (! $withdrawal) {
                    $withdrawal = AffiliateWithdraw::where('payment_id', $gatewayId)->lockForUpdate()->first();
                    $isAffiliate = true;
                }

                if (! $withdrawal && $externalRef !== '') {
                    $withdrawal = Withdrawal::where('payment_id', $externalRef)->lockForUpdate()->first();
                    $isAffiliate = false;

                    if (! $withdrawal) {
                        $withdrawal = AffiliateWithdraw::where('payment_id', $externalRef)->lockForUpdate()->first();
                        $isAffiliate = true;
                    }
                }

                if (! $withdrawal) {
                    Log::warning('[GERAPIX] cash-out webhook: saque não encontrado', [
                        'payment_id' => $gatewayId,
                        'external_reference' => $externalRef,
                        'status' => $statusNorm,
                    ]);

                    return false;
                }

                self::updateTransactionGatewayMetaGeraPix(
                    transactionId: $gatewayId,
                    gatewayStatus: $statusNorm,
                    gatewayResponse: $payload
                );

                if (self::isApprovedStatus($statusNorm)) {
                    $withdrawal->status = 1;
                    $withdrawal->save();
                    return true;
                }

                if (self::isCancelledStatus($statusNorm)) {
                    if ((int) $withdrawal->status !== 1 && (int) $withdrawal->status !== 2) {
                        $withdrawal->status = 2;
                        $withdrawal->save();

                        $wallet = Wallet::where('user_id', $withdrawal->user_id)->lockForUpdate()->first();
                        if ($wallet) {
                            if ($isAffiliate) {
                                $wallet->refer_rewards = (float) ($wallet->refer_rewards ?? 0) + (float) $withdrawal->amount;
                            } else {
                                $wallet->balance_withdrawal = (float) $wallet->balance_withdrawal + (float) $withdrawal->amount;
                            }
                            $wallet->save();
                        }
                    }

                    return true;
                }

                if ((int) $withdrawal->status !== 1 && (int) $withdrawal->status !== 2) {
                    $withdrawal->status = 9;
                    $withdrawal->save();
                }

                return true;
            });
        } catch (\Throwable $e) {
            \App\Support\GatewayLog::exception('GERAPIX', 'exceção no webhook de saque', $e, [
                'payment_id' => $gatewayId,
                'status'     => $statusNorm,
            ]);

            return false;
        }
    }

    private static function normalizeGatewayStatusGeraPix(?string $status): ?string
    {
        $status = mb_strtolower(trim((string) $status));

        if ($status === '') {
            return null;
        }

        if (self::isApprovedStatus($status)) {
            return 'REALIZADO';
        }

        if (self::isCancelledStatus($status)) {
            return 'CANCELADO';
        }

        return 'PENDENTE';
    }

    private static function updateTransactionGatewayMetaGeraPix(
        ?string $transactionId,
        ?string $gatewayStatus = null,
        ?array $gatewayResponse = null
    ): void {
        $transactionId = trim((string) $transactionId);

        if ($transactionId === '') {
            return;
        }

        $tx = Transaction::where('payment_id', $transactionId)->first();
        if (! $tx) {
            return;
        }

        if ($gatewayStatus !== null && trim((string) $gatewayStatus) !== '') {
            $tx->gateway_status = self::normalizeGatewayStatusGeraPix($gatewayStatus);
        }

        if ($gatewayResponse !== null) {
            $tx->gateway_response = self::encodeGatewayResponseGeraPix($gatewayResponse);

            $pixCode = self::extractPixCodeFromPayloadGeraPix($gatewayResponse);
            if ($pixCode && empty($tx->pix_copia_e_cola)) {
                $tx->pix_copia_e_cola = $pixCode;
            }

            $qrBase64 = self::extractQrCodeBase64FromPayloadGeraPix($gatewayResponse);
            if ($qrBase64 && empty($tx->qr_code_base64)) {
                $tx->qr_code_base64 = $qrBase64;
            }
        }

        if (! empty($tx->pix_copia_e_cola) && empty($tx->qr_received_at)) {
            $tx->qr_received_at = Carbon::now();
        }

        $tx->save();
    }

    private static function extractPixCodeFromPayloadGeraPix(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'qr_code'),
            data_get($payload, 'data.qr_code'),
            data_get($payload, 'pix_copia_e_cola'),
            data_get($payload, 'data.pix_copia_e_cola'),
        ];

        foreach ($candidates as $value) {
            $value = is_string($value) ? trim($value) : null;
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    private static function extractQrCodeBase64FromPayloadGeraPix(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'qr_code_base64'),
            data_get($payload, 'data.qr_code_base64'),
            data_get($payload, 'base64'),
            data_get($payload, 'data.base64'),
        ];

        foreach ($candidates as $value) {
            $value = is_string($value) ? trim($value) : null;
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    private static function encodeGatewayResponseGeraPix(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        try {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return null;
        }
    }
}