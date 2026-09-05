<?php

namespace App\Services\Gateways\Drivers;

use App\Helpers\Core;
use App\Helpers\Core as Helper;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Gateways\Contracts\PaymentGateway;
use App\Services\Gateways\ForceOnePay\ForceOnePayClient;
use App\Support\GatewayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Driver do ForceOnePay.
 *
 * ESTE GATEWAY É DIFERENTE DE TODOS OS OUTROS, e é importante saber por quê.
 *
 * Nos demais, nada é creditado antes de confirmar na fonte (getTransaction) ou
 * de validar uma assinatura HMAC. O ForceOnePay não oferece NENHUM dos dois:
 * a API tem só 4 rotas (token, criar, transferir, saldo) — não existe consulta
 * de transação nem estorno — e a doc oficial diz textualmente que a assinatura
 * do webhook é "Não informada".
 *
 * A defesa possível, então, é a URL: nós definimos o endereço do webhook a cada
 * requisição (campo `webhook`), e colocamos nele um SEGREDO aleatório único por
 * transação. Isso dá duas coisas de uma vez:
 *   1. Autenticidade — só nós e o provedor conhecemos aquela URL.
 *   2. Correlação — a doc não diz se o `idTransaction` do webhook é o `txid` ou
 *      o `codigo`; com a URL secreta não precisamos saber, ela já identifica a
 *      transação.
 * Somado ao valor conferido contra a NOSSA base e à trava status 0->1 do
 * finalizador (replay-safe), é o melhor que este provedor permite.
 *
 * O segredo do depósito mora em transactions.idUnico; o do saque é o próprio
 * uuid (UUIDv4) em withdrawals.payment_id.
 */
class ForceOnePayGateway implements PaymentGateway
{
    public function __construct(private readonly ForceOnePayClient $client)
    {
    }

    public function key(): string
    {
        return 'forceonepay';
    }

    public function label(): string
    {
        return 'ForceOnePay';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** A doc só documenta CPF; os demais tipos não estão informados. */
    public function supportedPixTypes(): array
    {
        return ['document'];
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

            // Segredo da URL do webhook: é a única prova de autenticidade que
            // este provedor permite. Gerado ANTES da chamada porque vai na
            // requisição. 48 chars aleatórios não são adivinháveis.
            $secret = Str::random(48);

            $res = $this->client->criarPix([
                'amount'  => $amount,
                'webhook' => url('/forceonepay/callback/' . $secret),
            ]);

            if (! $res->successful() || ! ForceOnePayClient::isOk($res->json('status'))) {
                GatewayLog::warning('FORCEONEPAY', 'cash-in recusado', $res->status(), [
                    // O erro vem como {status:0, message:"..."} — sem `data`.
                    'message' => $res->json('message'),
                ]);

                return response()->json(['error' => 'Não foi possível gerar o PIX.'], 500);
            }

            $data   = (array) ($res->json('data') ?? []);
            $txid   = (string) ($data['txid'] ?? '');
            $qrcode = (string) ($data['qrcode'] ?? '');

            if ($txid === '' || $qrcode === '') {
                GatewayLog::warning('FORCEONEPAY', 'resposta sem dados de PIX', null, $data);

                return response()->json(['error' => 'Gateway não retornou dados suficientes.'], 500);
            }

            $this->persist($txid, $secret, $amount, $request, $qrcode, $data);

            return response()->json([
                'status'        => true,
                'idTransaction' => $txid,
                'qrcode'        => $qrcode,
                'qrCodeBase64'  => null,
            ]);
        } catch (\Throwable $e) {
            GatewayLog::exception('FORCEONEPAY', 'exceção no cash-in', $e);

            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    private function persist(
        string $txid,
        string $secret,
        float $amount,
        Request $request,
        string $qrcode,
        array $gatewayResponse
    ): void {
        $user   = auth('api')->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        Transaction::create([
            'payment_id'       => $txid,
            'user_id'          => $user->id,
            'payment_method'   => 'pix',
            'gateway'          => $this->key(),
            'price'            => $amount,
            'currency'         => 'BRL',
            'status'           => 0,
            'idUnico'          => $secret,   // segredo da URL do webhook
            'gateway_status'   => 'pending',
            'pix_copia_e_cola' => $qrcode,
            // O segredo NÃO vai para o gateway_response: ele é credencial, e
            // esse campo é lido no admin e em relatórios.
            'gateway_response' => json_encode(
                array_diff_key($gatewayResponse, ['webhook' => null]),
                JSON_UNESCAPED_UNICODE
            ),
            'qr_received_at'   => now(),
            'client_ip'        => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'fbp'              => $request->input('fbp') ?? $request->cookie('_fbp'),
            'fbc'              => $request->input('fbc') ?? $request->cookie('_fbc'),
            'source_url'       => $request->headers->get('Origin') ?? $request->headers->get('Referer'),
        ]);

        Deposit::create([
            'payment_id' => $txid,
            'user_id'    => $user->id,
            'amount'     => $amount,
            'type'       => 'pix',
            'currency'   => $wallet->currency,
            'symbol'     => $wallet->symbol,
            'status'     => 0,
        ]);

        \App\Services\BetCrm\BetCrmService::sendDeposit($user, $amount, 'dep_' . $txid, [
            'status'   => \App\Services\BetCrm\BetCrmService::STATUS_PENDING,
            'method'   => 'pix',
            'provider' => $this->key(),
            'currency' => 'BRL',
        ]);
    }

    /**
     * Saque.
     *
     * O `uuid` é gerado por nós e guardado ANTES da chamada — a doc manda
     * "salvar antes de alterar saldo" e diz que a política de idempotência da
     * API é desconhecida. Por isso: uma tentativa só, sem retry automático.
     * Timeout NÃO é falha (o PIX pode ter saído): fica em processamento e o
     * webhook decide. Reenviar às cegas poderia pagar duas vezes.
     *
     * O mesmo uuid vai na URL do webhook, servindo de segredo e de correlação.
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
            if ($amount <= 0) {
                return false;
            }

            // A doc só documenta CPF como type_key.
            $tipo = strtolower(trim((string) ($pixType ?: $w->pix_type)));
            if (! in_array($tipo, ['document', 'cpf', 'cnpj'], true)) {
                GatewayLog::warning('FORCEONEPAY', 'tipo de chave não suportado (a doc só documenta CPF)', null, [
                    'withdrawal' => $w->id, 'pix_type' => $tipo,
                ]);

                return false;
            }

            // uuid estável por saque: reusa o de uma tentativa anterior, senão o
            // webhook antigo apontaria para uma URL que não existe mais.
            $uuid = $w->payment_id ?: (string) Str::uuid();

            $w->status     = 9;
            $w->payment_id = $uuid;
            $w->gateway    = $this->key();
            $w->save();

            $res = $this->client->transferirPix([
                'uuid'     => $uuid,
                'amount'   => $amount,
                'type_key' => strlen(Helper::soNumero((string) $w->pix_key)) === 14 ? 'CNPJ' : 'CPF',
                'key'      => (string) $w->pix_key,
                'webhook'  => url('/forceonepay/callback/wd/' . $uuid),
            ]);

            if (! $res->successful() || ! ForceOnePayClient::isOk($res->json('status'))) {
                GatewayLog::warning('FORCEONEPAY', 'cash-out recusado', $res->status(), [
                    'withdrawal' => $w->id, 'message' => $res->json('message'),
                ]);

                // Recusa explícita do provedor: não saiu pagamento, pode voltar
                // para pendente. O uuid é preservado para um reenvio usar o mesmo.
                $w->status = 0;
                $w->save();

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            GatewayLog::exception('FORCEONEPAY', 'exceção no cash-out', $e);

            // Exceção (timeout/rede) = estado INDEFINIDO, nunca "falhou": fica em
            // 9 e o webhook decide. Voltar para 0 aqui permitiria uma segunda
            // tentativa em cima de um PIX que talvez já tenha saído.
            return false;
        }
    }
}
