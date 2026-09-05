<?php



namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsApi
{
    protected string $pixelId;
    protected string $accessToken;
    protected ?string $testEventCode;
    protected string $apiVersion;
    protected bool $enabled;
    protected bool $debug; 

    public function __construct()
    {
        $this->pixelId       = (string) config('services.meta_capi.pixel_id', '');
        $this->accessToken   = (string) config('services.meta_capi.access_token', '');
        $this->testEventCode = config('services.meta_capi.test_event_code');
        $this->apiVersion    = (string) config('services.meta_capi.version', 'v18.0');
        $this->enabled       = (bool) config('services.meta_capi.enabled', false);
        $this->debug         = (bool) config('services.meta_capi.debug', false); 

        if ($this->debug) {
            Log::info('[META_CAPI][__construct] Serviço inicializado', [
                'pixel_id'         => $this->pixelId,
                'version'          => $this->apiVersion,
                'enabled'          => $this->enabled,
                'cfg_token_prefix' => substr($this->accessToken, 0, 20),
                'cfg_token_length' => strlen($this->accessToken),
                'cfg_token_sha1'   => sha1($this->accessToken),
            ]);
        }
    }


    public function sendEvent(
        string $eventName,
        array $userData,
        array $customData = [],
        ?string $eventId = null,
        array $context = []
    ): void {
        if ($this->debug) {
            Log::info('[META_CAPI][sendEvent] Iniciando envio de evento', [
                'event_name' => $eventName,
            ]);
        }


        if (!$this->enabled || empty($this->pixelId) || empty($this->accessToken)) {
            if ($this->debug) {
                Log::warning('[META_CAPI][sendEvent] Desabilitado ou sem credenciais', [
                    'enabled'          => $this->enabled,
                    'pixel_id'         => $this->pixelId,
                    'has_token'        => !empty($this->accessToken),
                    'cfg_token_length' => strlen($this->accessToken),
                ]);
            }
            return;
        }

        try {

            $normalizedUserData = $this->buildUserData($userData);


            if (!empty($context['client_ip'])) {
                $normalizedUserData['client_ip_address'] = $context['client_ip'];
            }

            if (!empty($context['user_agent'])) {
                $normalizedUserData['client_user_agent'] = $context['user_agent'];
            }


            $payloadEvent = [
                'event_name'       => $eventName,
                'event_time'       => time(),
                'event_id'         => $eventId ?? uniqid($eventName . '_', true),
                'event_source_url' => $context['event_source_url'] ?? null,
                'action_source'    => $context['action_source'] ?? 'website',
                'user_data'        => $normalizedUserData,
                'custom_data'      => $customData,
            ];

            $body = [
                'data' => [$payloadEvent],
            ];


            $isTestEvent = (bool) ($context['test_event'] ?? false);
            if ($isTestEvent && $this->testEventCode) {
                $body['test_event_code'] = $this->testEventCode;
            }

            $endpoint = "https://graph.facebook.com/{$this->apiVersion}/{$this->pixelId}/events";

            if ($this->debug) {
                Log::info('[META_CAPI][sendEvent] REQUEST DUMP', [
                    'endpoint'         => $endpoint,
                    'body'             => $body,
                    'cfg_token_prefix' => substr($this->accessToken, 0, 25),
                    'cfg_token_length' => strlen($this->accessToken),
                    'cfg_token_sha1'   => sha1($this->accessToken),
                ]);
            }

            $response = Http::timeout(5)
                // Mesma saída IPv4 de todas as outras integrações: o IP de
                // origem dos eventos fica estável e previsível.
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->asJson()
                ->post($endpoint, $body + [
                    'access_token' => $this->accessToken,
                ]);

            if ($this->debug) {
                Log::info('[META_CAPI][sendEvent] RESPONSE DUMP', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            if (!$response->successful()) {

                Log::warning('[META_CAPI] Falha ao enviar evento', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                    'event'    => $payloadEvent,
                    'endpoint' => $endpoint,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[META_CAPI] Erro ao enviar evento: ' . $e->getMessage(), [
                'event_name' => $eventName,
            ]);
        }
    }


    protected function buildUserData(array $userData): array
    {

        $fieldMap = [
            'email'      => 'em',
            'phone'      => 'ph',
            'first_name' => 'fn',
            'last_name'  => 'ln',

        ];


        $hashable = [
            'em',
            'ph',
            'fn',
            'ln',
            'external_id',
        ];

        $normalized = [];

        foreach ($userData as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }


            if (\in_array($key, ['fbp', 'fbc'], true)) {
                $normalized[$key] = (string) $value;
                continue;
            }


            $originalKey = $key;
            $mappedKey   = $fieldMap[$key] ?? $key; 

            if (\in_array($mappedKey, $hashable, true)) {
                $normalized[$mappedKey] = hash(
                    'sha256',
                    $this->normalizeValue((string) $value, $originalKey)
                );
            } else {
                $normalized[$mappedKey] = $value;
            }
        }

        if ($this->debug) {
            Log::info('[META_CAPI][buildUserData] USER DATA NORMALIZADO', [
                'input'      => $userData,
                'normalized' => $normalized,
            ]);
        }

        return $normalized;
    }


    protected function normalizeValue(string $value, string $field): string
    {
        $original = $value;
        $value = trim($value);

        switch ($field) {
            case 'email':
                $normalized = mb_strtolower($value);
                break;

            case 'phone':

                $normalized = preg_replace('/\D+/', '', $value) ?? '';
                break;

            case 'first_name':
            case 'last_name':

                $normalized = mb_strtolower(preg_replace('/\s+/', '', $value) ?? '');
                break;

            default:
                $normalized = mb_strtolower($value);
                break;
        }

        if ($this->debug) {
            Log::info('[META_CAPI][normalizeValue] Campo normalizado', [
                'field'    => $field,
                'original' => $original,
                'final'    => $normalized,
            ]);
        }

        return $normalized;
    }
}
