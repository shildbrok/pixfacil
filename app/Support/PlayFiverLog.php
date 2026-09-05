<?php



namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class PlayFiverLog
{
    public static function warning(string $event, ?int $status = null, $body = null, array $context = []): void
    {
        self::write('warning', $event, $status, $body, $context);
    }

    public static function error(string $event, ?int $status = null, $body = null, array $context = []): void
    {
        self::write('error', $event, $status, $body, $context);
    }

    public static function exception(string $event, Throwable $e, array $context = []): void
    {
        self::write('error', $event, null, [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ], $context);
    }

    private static function write(string $level, string $event, ?int $status, $body, array $context): void
    {
        $bodyArray = self::normalizeBody($body);
        $reason = self::reason($status, $bodyArray);

        $payload = array_filter(array_merge([
            'provider' => 'PLAYFIVER',
            'event' => $event,
            'status' => $status,
            'reason' => $reason,
            'action' => self::action($reason),
            'playfiver_message' => self::messageFromBody($bodyArray),
            'playfiver_code' => self::codeFromBody($bodyArray),
        ], self::sanitize($context)), static fn ($value) => $value !== null && $value !== '' && $value !== []);

        Log::$level('[PLAYFIVER] ' . $event . ': ' . $reason, $payload);
    }

    public static function reason(?int $status, $body): string
    {
        $body = self::normalizeBody($body);
        $message = mb_strtolower((string) self::messageFromBody($body));
        $text = mb_strtolower(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $full = trim($message . ' ' . $text);

        if ($status === 403 || str_contains($full, 'ip não permitido') || str_contains($full, 'ip nao permitido') || str_contains($full, 'ip not allowed') || str_contains($full, 'forbidden')) {
            return 'IP não permitido pela PlayFiver';
        }

        if (str_contains($full, 'agenttoken.required') || str_contains($full, 'o agenttoken é obrigatório') || str_contains($full, 'agenttoken obrig')) {
            return 'agentToken não enviado para a PlayFiver';
        }

        if (str_contains($full, 'agenttoken.exists') || str_contains($full, 'agenttoken não existe') || str_contains($full, 'agenttoken nao existe') || str_contains($full, 'esse agente não existe') || str_contains($full, 'esse agente nao existe')) {
            return 'Agente PlayFiver não existe ou está incorreto';
        }

        if (str_contains($full, 'secretkey.required') || str_contains($full, 'o secretkey é obrigatório') || str_contains($full, 'secretkey obrig')) {
            return 'secretKey não enviada para a PlayFiver';
        }

        if (str_contains($full, 'secretkey.exists') || str_contains($full, 'secretkey não existe') || str_contains($full, 'secretkey nao existe')) {
            return 'secretKey PlayFiver não existe ou está incorreta';
        }

        if (str_contains($full, 'user_code.required') || str_contains($full, 'user_code é obrigatório') || str_contains($full, 'user_code obrig')) {
            return 'user_code não enviado para a PlayFiver';
        }

        if (str_contains($full, 'game_code.required') || str_contains($full, 'game_code é obrigatório') || str_contains($full, 'game_code obrig')) {
            return 'game_code não enviado para a PlayFiver';
        }

        if (str_contains($full, 'game_code.exists') || str_contains($full, 'game_code não existe') || str_contains($full, 'game_code nao existe')) {
            return 'Jogo não existe na PlayFiver';
        }

        if (str_contains($full, 'jogo está em manutenção') || str_contains($full, 'jogo esta em manutenção') || str_contains($full, 'jogo esta em manutencao') || str_contains($full, 'game maintenance') || str_contains($full, 'game em manutenção')) {
            return 'Jogo em manutenção na PlayFiver';
        }

        if (str_contains($full, 'provedor em manutenção') || str_contains($full, 'provedor em manutencao') || str_contains($full, 'provider maintenance')) {
            return 'Provedor em manutenção na PlayFiver';
        }

        if (str_contains($full, 'estamos em manutenção') || str_contains($full, 'estamos em manutencao') || str_contains($full, 'we are in maintenance')) {
            return 'PlayFiver em manutenção';
        }

        if (str_contains($full, 'usuário banido') || str_contains($full, 'usuario banido') || str_contains($full, 'user banned') || str_contains($full, 'banned')) {
            return 'Usuário banido na PlayFiver';
        }

        if (str_contains($full, 'saldo insuficiente na carteira') || str_contains($full, 'insufficient balance') || str_contains($full, 'insufficient funds')) {
            return 'Saldo insuficiente na carteira PlayFiver/agregador';
        }

        if (str_contains($full, 'modo influencer') || str_contains($full, 'influencer ativado') || str_contains($full, 'influencer.boolean')) {
            return 'Modo influencer ativo ou parâmetro influencer inválido';
        }

        if (str_contains($full, 'user_balance.required') || str_contains($full, 'user_balance é obrigatório') || str_contains($full, 'user_balance obrig')) {
            return 'user_balance não enviado para a PlayFiver';
        }

        if (str_contains($full, 'user_balance.numeric') || str_contains($full, 'user_balance precisa ser') || str_contains($full, 'user_balance numer')) {
            return 'user_balance inválido';
        }

        if (str_contains($full, 'game_original.boolean') || str_contains($full, 'parametro "original"') || str_contains($full, 'parâmetro "original"')) {
            return 'Parâmetro game_original inválido';
        }

        if ($status === 422) {
            return 'Requisição recusada pela PlayFiver por validação ou manutenção';
        }

        if ($status !== null && $status >= 500) {
            return 'Falha operacional da PlayFiver';
        }

        if ($status !== null && $status >= 400) {
            return 'Requisição recusada pela PlayFiver';
        }

        return 'Falha na integração PlayFiver';
    }

    private static function action(string $reason): string
    {
        $lower = mb_strtolower($reason);

        if (str_contains($lower, 'ip')) {
            return 'Cadastre o IPv4 do servidor na whitelist da PlayFiver.';
        }

        if (str_contains($lower, 'agent') || str_contains($lower, 'secret')) {
            return 'Confira agentToken, secretKey, agent_code e ambiente configurado em Agregador de Jogos.';
        }

        if (str_contains($lower, 'jogo não existe')) {
            return 'Sincronize o catálogo ou confira se game_code/game_original estão corretos.';
        }

        if (str_contains($lower, 'manutenção')) {
            return 'Não é erro do usuário. Aguarde a PlayFiver/provedor/jogo voltar ou desative o jogo no painel.';
        }

        if (str_contains($lower, 'banido')) {
            return 'Verifique o cadastro/risco do usuário na PlayFiver antes de liberar novo lançamento.';
        }

        if (str_contains($lower, 'saldo insuficiente')) {
            return 'Recarregue a carteira/agregador PlayFiver ou bloqueie lançamento até haver saldo operacional.';
        }

        if (str_contains($lower, 'influencer')) {
            return 'Confira flag influencer do usuário e regra desse modo na PlayFiver.';
        }

        if (str_contains($lower, 'user_balance')) {
            return 'Confira cálculo de saldo do usuário e envie número decimal válido para a PlayFiver.';
        }

        if (str_contains($lower, 'game_original')) {
            return 'Envie game_original como boolean true/false, não string ou número inválido.';
        }

        if (str_contains($lower, 'user_code')) {
            return 'Confira se o usuário autenticado tem e-mail/user_code válido.';
        }

        return 'Leia a mensagem da PlayFiver e confira configuração, jogo, usuário e saldo operacional.';
    }

    public static function sanitize($data)
    {
        if (! is_array($data)) {
            return is_string($data) ? self::truncate($data) : $data;
        }

        $sensitive = [
            'authorization', 'bearer', 'token', 'agentToken', 'agent_token', 'agent_secret', 'secret', 'secretKey', 'secret_key',
            'access_token', 'password', 'cpf', 'cnpj', 'document', 'email', 'user_code', 'launch_url', 'url'
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $k = (string) $key;
            $normalized = strtolower(str_replace(['-', '_'], '', $k));

            if (is_array($value)) {
                $result[$key] = self::sanitize($value);
                continue;
            }

            $isSensitive = false;
            foreach ($sensitive as $item) {
                if ($normalized === strtolower(str_replace(['-', '_'], '', $item))) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$key] = self::mask((string) $value);
                continue;
            }

            $result[$key] = is_string($value) ? self::truncate($value) : $value;
        }

        return $result;
    }

    private static function normalizeBody($body): array
    {
        if (is_array($body)) {
            return $body;
        }

        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : ['message' => self::truncate($body)];
        }

        return [];
    }

    private static function messageFromBody(array $body): ?string
    {
        $value = $body['message']
            ?? $body['msg']
            ?? $body['mensagem']
            ?? data_get($body, 'error.message')
            ?? data_get($body, 'error')
            ?? data_get($body, 'errors.0')
            ?? data_get($body, 'data.message');

        if (is_array($value)) {
            $value = implode(' | ', array_filter(array_map(static fn ($item) => is_scalar($item) ? (string) $item : null, $value)));
        }

        return is_scalar($value) ? self::truncate((string) $value) : null;
    }

    private static function codeFromBody(array $body): ?string
    {
        $value = $body['code'] ?? $body['statusCode'] ?? data_get($body, 'error.code');
        return is_scalar($value) ? (string) $value : null;
    }

    private static function mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $length = mb_strlen($value);
        if ($length <= 8) {
            return str_repeat('*', max(3, $length));
        }

        return mb_substr($value, 0, 4) . str_repeat('*', min(16, max(6, $length - 8))) . mb_substr($value, -4);
    }

    private static function truncate(string $value, int $limit = 500): string
    {
        $value = trim($value);
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . '...' : $value;
    }
}
