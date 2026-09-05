<?php



namespace App\Support;

use Illuminate\Support\Facades\Log;

class GatewayLog
{
    public static function warning(string $gateway, string $event, ?int $status = null, $body = null, array $context = []): void
    {
        self::write('warning', $gateway, $event, $status, $body, $context);
    }

    public static function error(string $gateway, string $event, ?int $status = null, $body = null, array $context = []): void
    {
        self::write('error', $gateway, $event, $status, $body, $context);
    }

    public static function exception(string $gateway, string $event, \Throwable $e, array $context = []): void
    {
        self::write('error', $gateway, $event, null, [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ], $context);
    }

    private static function write(string $level, string $gateway, string $event, ?int $status, $body, array $context): void
    {
        $gateway = strtoupper($gateway);
        $bodyArray = self::normalizeBody($body);
        $reason = self::reason($gateway, $status, $bodyArray);

        $payload = array_filter(array_merge([
            'gateway' => $gateway,
            'event' => $event,
            'status' => $status,
            'reason' => $reason,
            'action' => self::action($reason),
            'gateway_message' => self::messageFromBody($bodyArray),
            'code' => self::codeFromBody($bodyArray),
        ], self::sanitize($context)), static fn ($value) => $value !== null && $value !== '' && $value !== []);

        Log::$level('[' . $gateway . '] ' . $event . ': ' . $reason, $payload);
    }

    public static function reason(string $gateway, ?int $status, $body): string
    {
        $body = self::normalizeBody($body);
        $text = mb_strtolower(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $message = mb_strtolower((string) self::messageFromBody($body));
        $full = trim($message . ' ' . $text);

        if (str_contains($full, 'object reference not set')) {
            return $gateway === 'DIGITOPAY'
                ? 'CPF inválido, inexistente ou recusado pela DigitoPay'
                : 'Documento inválido ou recusado pelo gateway';
        }

        if (str_contains($full, 'cpf') && (str_contains($full, 'invalid') || str_contains($full, 'inválid') || str_contains($full, 'invalido'))) {
            return 'CPF/documento inválido';
        }

        if (str_contains($full, 'email') && (str_contains($full, 'invalid') || str_contains($full, 'inválid') || str_contains($full, 'invalido'))) {
            return 'E-mail inválido';
        }

        if (str_contains($full, 'ip') && (str_contains($full, 'não autorizado') || str_contains($full, 'nao autorizado') || str_contains($full, 'not authorized') || str_contains($full, 'unauthorized') || str_contains($full, 'whitelist'))) {
            return 'IP do servidor não permitido no gateway';
        }

        if (str_contains($full, 'credenciais inválidas') || str_contains($full, 'credenciais invalidas') || str_contains($full, 'api key inválida') || str_contains($full, 'api key invalida') || str_contains($full, 'authentication_error')) {
            return 'Credenciais do gateway inválidas';
        }

        if ($status === 401 || $status === 403) {
            return str_contains($full, 'ip') ? 'IP do servidor não permitido no gateway' : 'Credenciais, token ou autorização inválida';
        }

        if ($status === 422 && (str_contains($full, 'valor solicitado fora do intervalo') || str_contains($full, 'mínimo') || str_contains($full, 'minimo') || str_contains($full, 'máximo') || str_contains($full, 'maximo'))) {
            return 'Valor fora do limite permitido pelo gateway';
        }

        if (str_contains($full, 'saldo insuficiente')) {
            return 'Saldo insuficiente na conta do gateway';
        }

        if (str_contains($full, 'outra chave pix') || str_contains($full, 'chave pix') && (str_contains($full, 'erro') || str_contains($full, 'inválid') || str_contains($full, 'invalido'))) {
            return 'Chave PIX inválida ou recusada';
        }

        if (str_contains($full, 'idempotency') || $status === 409) {
            return 'Requisição duplicada ou idempotência em processamento';
        }

        if ($status === 429 || str_contains($full, 'rate limit') || str_contains($full, 'too many')) {
            return 'Limite de requisições excedido';
        }

        if (str_contains($full, 'timeout') || str_contains($full, 'timed out') || str_contains($full, 'could not resolve') || str_contains($full, 'connection') || str_contains($full, 'curl error') || str_contains($full, 'ssl')) {
            return 'Falha de conexão com o gateway';
        }

        if ($status !== null && $status >= 500) {
            return $gateway === 'DIGITOPAY' ? 'Credenciais inválidas ou instabilidade na DigitoPay' : 'Instabilidade interna do gateway';
        }

        if ($status !== null && $status >= 400) {
            return 'Requisição recusada pelo gateway';
        }

        return 'Falha no gateway de pagamento';
    }

    private static function action(string $reason): string
    {
        $lower = mb_strtolower($reason);

        if (str_contains($lower, 'cpf') || str_contains($lower, 'documento')) {
            return 'Peça ao usuário para conferir CPF/documento e tente novamente.';
        }

        if (str_contains($lower, 'e-mail')) {
            return 'Corrija o e-mail do cadastro do usuário.';
        }

        if (str_contains($lower, 'ip')) {
            return 'Libere o IPv4 do servidor no painel do gateway.';
        }

        if (str_contains($lower, 'credenciais') || str_contains($lower, 'token') || str_contains($lower, 'autorização')) {
            return 'Confira API URL, client_id, secret, API key, senha e ambiente produção/sandbox.';
        }

        if (str_contains($lower, 'valor fora')) {
            return 'Ajuste mínimo/máximo no painel ou informe valor dentro do limite do gateway.';
        }

        if (str_contains($lower, 'saldo insuficiente')) {
            return 'Adicione saldo na conta do gateway antes de aprovar novos saques.';
        }

        if (str_contains($lower, 'pix')) {
            return 'Revise tipo da chave PIX, titularidade e documento do recebedor.';
        }

        if (str_contains($lower, 'conexão')) {
            return 'Teste conectividade do servidor e confirme se o gateway está online.';
        }

        return 'Verifique a mensagem do gateway e os dados da operação.';
    }

    public static function sanitize($data)
    {
        if (! is_array($data)) {
            return is_string($data) ? self::truncate($data) : $data;
        }

        $sensitive = [
            'authorization', 'bearer', 'token', 'access_token', 'accessToken', 'secret', 'client_secret', 'clientSecret',
            'password', 'api_key', 'apiKey', 'x-api-key', 'cpf', 'cnpj', 'document', 'taxid', 'taxId', 'pixkey', 'pixKey',
            'pix_copia_e_cola', 'pixCopiaECola', 'pixCopyPaste', 'qrcode', 'qrCode', 'pixQrCode', 'qrCodeBase64',
            'qr_code_base64', 'pixQrCodeImage', 'raw', 'body_base64', 'base64'
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
        $value = $body['message'] ?? $body['mensagem'] ?? data_get($body, 'error.message') ?? data_get($body, 'error') ?? data_get($body, 'data.message');
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
