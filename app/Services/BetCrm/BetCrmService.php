<?php



namespace App\Services\BetCrm;

use App\Jobs\SendBetCrmEvent;
use App\Models\BetcrmSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


/**
 * Integração de saída com o BetCRM.
 *
 * REGRA DE OURO: o CRM é acessório. NENHUMA falha dele — HTTP, dado inesperado,
 * banco fora do ar — pode quebrar cadastro, login, depósito ou saque, e ele NÃO
 * gera log. Por isso todo método público roda dentro de self::safely(), que
 * engole qualquer exceção em silêncio e segue o fluxo normal.
 */
class BetCrmService
{
    /** Plataformas que o contrato aceita em `device`. Fora disso, 422. */
    public const DEVICE_ANDROID = 'android';
    public const DEVICE_IOS     = 'ios';
    public const DEVICE_DESKTOP = 'desktop';

    /** Status de transação que o BetCRM conta como receita. */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';


    /**
     * Cerca de proteção do CRM: roda o trabalho e engole QUALQUER erro sem logar.
     * O ponto é justamente esse — o CRM nunca derruba o fluxo nem polui os logs.
     */
    private static function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            // Silêncio proposital: o CRM é acessório e não deve gerar ruído.
        }
    }


    public static function settings(): ?BetcrmSetting
    {
        return BetcrmSetting::query()->find(1);
    }


    public static function enabled(): bool
    {
        $s = self::settings();

        return $s
            && (bool) $s->enabled
            && filled($s->client_id)
            && filled($s->client_secret);
    }

    private static function baseUrl(BetcrmSetting $s): string
    {
        return rtrim((string) ($s->base_url ?: 'https://betcrm.io/api/v1'), '/');
    }


    /**
     * O `site` é parte da IDENTIDADE do lead no BetCRM: trocar o formato cria
     * leads duplicados. Derivar de app.url significa que mexer na APP_URL
     * duplicaria a base inteira em silêncio, então o valor pode ser fixado nas
     * configurações. O fallback mantém o que já vinha sendo enviado.
     */
    private static function site(): ?string
    {
        $pinned = trim((string) (self::settings()?->site ?? ''));
        if ($pinned !== '') {
            return $pinned;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }

    private static function externalId(User $user): string
    {
        return (string) $user->id;
    }

    private static function nowIso(): string
    {
        return now()->utc()->format('Y-m-d\TH:i:s\Z');
    }


    private static function callbackUrl(BetcrmSetting $s): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/betcrm/lead-stats';
    }


    /**
     * `device` é obrigatório no cadastro e o BetCRM não tem como descobrir: a
     * chamada sai do nosso servidor, então o que chegaria lá é o nosso cliente
     * HTTP, não o aparelho do jogador. Auto-declaramos pelo user-agent de quem
     * se cadastrou. Sem user-agent não há o que deduzir, então cai em desktop
     * (omitir o campo seria 422, lead perdido).
     */
    public static function deviceFromRequest(?Request $request): string
    {
        $ua = trim((string) ($request?->userAgent() ?? ''));

        if ($ua === '') {
            return self::DEVICE_DESKTOP;
        }

        if (preg_match('/android/i', $ua)) {
            return self::DEVICE_ANDROID;
        }

        // iPadOS 13+ se apresenta como Macintosh; nesse caso vira desktop mesmo.
        if (preg_match('/iphone|ipad|ipod/i', $ua)) {
            return self::DEVICE_IOS;
        }

        return self::DEVICE_DESKTOP;
    }


    /** Identidade durável do jogador em /leads e /events. */
    private static function identity(User $user, BetcrmSetting $s): array
    {
        $data = [
            'external_id' => self::externalId($user),
            'site'        => self::site(),
        ];

        $bcRef = trim((string) ($user->betcrm_ref ?? ''));
        if ($bcRef !== '') {
            $data['bc_ref'] = $bcRef;
        }

        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }


    /**
     * Em /deposits e /withdrawals o jogador é `user_external_id`. O `external_id`
     * ali é o id da TRANSAÇÃO (alternativa ao idempotency_key) — mandar o id do
     * usuário nesse campo é o que o contrato manda evitar.
     */
    private static function transactionIdentity(User $user, BetcrmSetting $s): array
    {
        $data = [
            'user_external_id' => self::externalId($user),
            'site'             => self::site(),
        ];

        $bcRef = trim((string) ($user->betcrm_ref ?? ''));
        if ($bcRef !== '') {
            $data['bc_ref'] = $bcRef;
        }

        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }


    private static function digitsOrNull(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }


    /**
     * O telefone é o canal do CRM: é por ele que o WhatsApp sai. Ele precisa ir
     * com DDI e só dígitos — telefone fora do formato não dá erro, o lead entra,
     * ocupa cota e simplesmente nunca casa com nada. Nós guardamos o número
     * formatado e sem DDI ("(11) 99999-9999"), então normalizamos aqui.
     */
    private static function phoneWithDdi(?string $value): ?string
    {
        $digits = self::digitsOrNull($value);
        if ($digits === null) {
            return null;
        }

        // Já veio com DDI 55 (12 = fixo, 13 = celular com o 9).
        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            return $digits;
        }

        // Nacional sem DDI: 10 = DDD + fixo, 11 = DDD + celular.
        if (in_array(strlen($digits), [10, 11], true)) {
            return '55' . $digits;
        }

        // Formato que não reconhecemos: manda os dígitos e deixa o CRM decidir,
        // em vez de descartar o contato do lead.
        return $digits;
    }


    /**
     * O contrato não tem lead_email/lead_phone soltos na transação: os dados do
     * jogador vão no objeto `lead`, usado para criar o lead caso ele ainda não
     * exista. Campo solto fora do contrato é descartado sem aviso.
     */
    private static function leadObject(User $user): array
    {
        $lead = array_filter([
            'external_id' => self::externalId($user),
            'name'        => $user->name ?? null,
            'email'       => $user->email ?? null,
            'phone'       => self::phoneWithDdi($user->phone ?? null),
            'cpf'         => self::digitsOrNull($user->cpf ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        return $lead ? ['lead' => $lead] : [];
    }


    private static function metadata(User $user): array
    {
        $meta = array_filter([
            'language'     => $user->language ?? null,
            'inviter_code' => $user->inviter_code ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $meta ? ['metadata' => $meta] : [];
    }



    public static function sendRegistration(User $user, ?Request $request = null): void
    {
        self::safely(function () use ($user, $request) {
            $s = self::settings();
            if (! self::enabled() || ! $s?->send_registration) {
                return;
            }

            // event_type=registration segue as mesmas regras do POST /leads:
            // site, name, phone, ip e device são obrigatórios.
            $payload = array_merge(self::identity($user, $s), self::metadata($user), [
                'event_type'   => 'registration',
                'name'         => $user->name ?? null,
                'phone'        => self::phoneWithDdi($user->phone ?? null),
                'email'        => $user->email ?? null,
                'ip'           => $request?->ip(),
                'device'       => self::deviceFromRequest($request),
                'bet_url'      => config('app.url'),
                'callback_url' => self::callbackUrl($s),
                'event_id'     => 'reg_' . $user->id,
                'occurred_at'  => self::nowIso(),
            ]);

            self::dispatch('/events', $payload);
        });
    }


    public static function sendLead(User $user, ?Request $request = null): void
    {
        self::safely(function () use ($user, $request) {
            $s = self::settings();
            if (! self::enabled() || ! $s?->send_registration) {
                return;
            }

            $cookie = fn (string $name) => $request?->cookie($name);

            $rich = array_filter([
                'name'         => $user->name ?? null,
                'email'        => $user->email ?? null,
                'phone'        => self::phoneWithDdi($user->phone ?? null),
                'cpf'          => self::digitsOrNull($user->cpf ?? null),
                'ip'           => $request?->ip(),
                'device'       => self::deviceFromRequest($request),
                'status'       => 'registered',
                'bet_url'      => config('app.url'),
                'callback_url' => self::callbackUrl($s),
                'fbp'          => $request?->input('fbp'),
                'fbc'          => $request?->input('fbc'),
                'fbclid'       => $cookie('betcrm_fbclid'),
                'gclid'        => $cookie('betcrm_gclid'),
                'utm_source'   => $cookie('betcrm_utm_source'),
                'utm_medium'   => $cookie('betcrm_utm_medium'),
                'utm_campaign' => $cookie('betcrm_utm_campaign'),
                'occurred_at'  => self::nowIso(),
            ], fn ($v) => $v !== null && $v !== '');

            self::dispatch('/leads', array_merge(
                self::identity($user, $s),
                self::metadata($user),
                $rich
            ));
        });
    }

    public static function sendLogin(User $user): void
    {
        self::safely(function () use ($user) {
            $s = self::settings();
            if (! self::enabled() || ! $s?->send_login) {
                return;
            }

            // login/app_install não repetem name/phone/ip/device: o jogador já
            // declarou tudo isso no cadastro, e a regra do CRM decide por lá.
            self::dispatch('/events', array_merge(self::identity($user, $s), [
                'event_type'  => 'login',
                'event_id'    => 'login_' . $user->id . '_' . now()->timestamp,
                'occurred_at' => self::nowIso(),
            ]));
        });
    }

    public static function sendAppInstall(User $user, ?string $platform = null): void
    {
        self::safely(function () use ($user, $platform) {
            $s = self::settings();
            if (! self::enabled() || ! $s?->send_app_install) {
                return;
            }

            self::dispatch('/events', array_merge(self::identity($user, $s), [
                'event_type'  => 'app_install',
                'metadata'    => ['platform' => $platform ?: 'pwa'],
                'event_id'    => 'app_' . $user->id,
                'occurred_at' => self::nowIso(),
            ]));
        });
    }

    /**
     * Mande `pending` quando o PIX é gerado e reenvie a MESMA chave com `paid`
     * na confirmação: é a virada para pago que conta a venda e dispara a
     * conversão na Meta. Reenviar um pago não manda outra conversão.
     */
    public static function sendDeposit(User $user, float $amount, string $idempotencyKey, array $ctx = []): void
    {
        self::safely(function () use ($user, $amount, $idempotencyKey, $ctx) {
            $s = self::settings();
            if (! self::enabled() || ! $s?->send_deposit || $amount <= 0) {
                return;
            }

            $payload = array_merge(
                self::transactionIdentity($user, $s),
                self::leadObject($user),
                array_filter([
                    'amount'          => round($amount, 2),
                    'currency'        => $ctx['currency'] ?? 'BRL',
                    'status'          => $ctx['status'] ?? self::STATUS_PAID,
                    'method'          => $ctx['method'] ?? 'pix',
                    'provider'        => $ctx['provider'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at'     => self::nowIso(),
                ], fn ($v) => $v !== null && $v !== '')
            );

            self::dispatch('/deposits', $payload);
        });
    }

    public static function sendWithdrawal(User $user, float $amount, string $idempotencyKey, array $ctx = []): void
    {
        self::safely(function () use ($user, $amount, $idempotencyKey, $ctx) {
            $s = self::settings();
            if (! self::enabled() || ! $s?->send_withdrawal || $amount <= 0) {
                return;
            }

            $payload = array_merge(
                self::transactionIdentity($user, $s),
                self::leadObject($user),
                array_filter([
                    'amount'          => round($amount, 2),
                    'currency'        => $ctx['currency'] ?? 'BRL',
                    'status'          => $ctx['status'] ?? self::STATUS_PAID,
                    'method'          => $ctx['method'] ?? 'pix',
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at'     => self::nowIso(),
                ], fn ($v) => $v !== null && $v !== '')
            );

            self::dispatch('/withdrawals', $payload);
        });
    }



    private static function dispatch(string $path, array $payload): void
    {
        // dispatchAfterResponse de propósito: não há worker de fila rodando
        // nesta instalação, então um dispatch normal deixaria o evento parado
        // na tabela jobs para sempre.
        SendBetCrmEvent::dispatchAfterResponse($path, $payload);
    }


    public static function postRaw(string $path, array $payload): void
    {
        // Todo o envio corre dentro do safely: nenhum erro de HTTP/rede vaza, e
        // nada é logado — o resultado do CRM não interessa ao fluxo do site.
        self::safely(function () use ($path, $payload) {
            $s = self::settings();
            if (! $s || ! filled($s->client_id) || ! filled($s->client_secret)) {
                return;
            }

            Http::withHeaders([
                'X-Client-Id'     => (string) $s->client_id,
                'X-Client-Secret' => (string) $s->client_secret,
                'Content-Type'    => 'application/json',
            ])
                // Este servidor tem saída IPv6 além da IPv4, e o CRM autentica
                // por credencial + (possível) allowlist de IP. Sem fixar v4, a
                // requisição pode sair por um IP que o CRM não conhece — e como
                // aqui nada é logado nem lançado, a falha seria invisível.
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(8)
                // Só tenta de novo o que tem chance de mudar: 5xx e o 429 de
                // rate limit. 422/402 e o 429 de cota não melhoram com retry.
                // throw:false — nunca lança; uma falha final é simplesmente ignorada.
                ->retry(3, 500, function ($exception, $request) {
                    $status = method_exists($exception, 'response')
                        ? optional($exception->response)->status()
                        : null;

                    return $status === null || $status >= 500;
                }, throw: false)
                ->post(self::baseUrl($s) . $path, $payload);
        });
    }
}
