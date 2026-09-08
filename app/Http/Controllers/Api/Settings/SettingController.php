<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\KycConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingController extends Controller
{
    private const CLIENT_MAX_AGE_SECONDS = 60;
    private const SERVER_CACHE_TTL = 86400;
    private const CACHE_KEY = 'api:settings:index:v5';

    public function index(Request $request)
    {
        try {
            $fingerprint = $this->currentFingerprint();
            $cached = Cache::get(self::CACHE_KEY);

            if (! $cached || ($cached['fingerprint'] ?? null) !== $fingerprint) {
                $lock = method_exists(Cache::getStore(), 'lock')
                    ? Cache::lock('lock:' . self::CACHE_KEY, 5)
                    : null;

                try {
                    if ($lock && $lock->get()) {
                        $cached = Cache::get(self::CACHE_KEY);

                        if (! $cached || ($cached['fingerprint'] ?? null) !== $fingerprint) {
                            $cached = $this->buildCachedPayload($fingerprint);
                            Cache::put(self::CACHE_KEY, $cached, self::SERVER_CACHE_TTL);
                        }
                    } else {
                        $cached = $this->buildCachedPayload($fingerprint);
                        Cache::put(self::CACHE_KEY, $cached, self::SERVER_CACHE_TTL);
                    }
                } finally {
                    if ($lock) {
                        try { $lock->release(); } catch (\Throwable) {}
                    }
                }
            }

            $ifNoneMatch = $request->header('If-None-Match');
            $ifModifiedSince = $request->header('If-Modified-Since');

            if ($ifNoneMatch && trim($ifNoneMatch) === $cached['etag']) {
                return response('', 304)
                    ->header('ETag', $cached['etag'])
                    ->header('Last-Modified', $cached['last_modified'])
                    ->header('Cache-Control', $this->clientCacheControl());
            }

            if ($ifModifiedSince && $this->httpDateToTimestamp($ifModifiedSince) >= $this->httpDateToTimestamp($cached['last_modified'])) {
                return response('', 304)
                    ->header('ETag', $cached['etag'])
                    ->header('Last-Modified', $cached['last_modified'])
                    ->header('Cache-Control', $this->clientCacheControl());
            }

            return response()
                ->json($cached['payload'], 200, [], JSON_UNESCAPED_UNICODE)
                ->header('ETag', $cached['etag'])
                ->header('Last-Modified', $cached['last_modified'])
                ->header('Cache-Control', $this->clientCacheControl());
        } catch (\Throwable $e) {
            report($e);

            return response()
                ->json(['setting' => $this->fallbackSettingPayload()])
                ->header('Cache-Control', $this->clientCacheControl());
        }
    }

    public function bust()
    {
        Cache::forget('api:settings:index:v3');
        Cache::forget('api:settings:index:v4');
        Cache::forget(self::CACHE_KEY);
        Cache::forget('custom');
        Cache::forget('custom_layout');

        return response()->json(['ok' => true]);
    }

    private function buildCachedPayload(string $fingerprint): array
    {
        $setting = null;

        try {
            $setting = \Helper::getSetting();
        } catch (\Throwable $e) {
            report($e);
            $setting = $this->fallbackSettingPayload();
        }

        $settingsUpdatedAt = $this->tableMaxUpdatedAt('settings');
        $customUpdatedAt = $this->tableMaxUpdatedAt('custom_layouts');

        $globalBust = (string) Cache::get('asset_version', 'v1');
        $contentTs = max(
            (int) strtotime((string) $settingsUpdatedAt),
            (int) strtotime((string) $customUpdatedAt)
        );
        $assetVersion = $globalBust . '-' . ($contentTs ?: time());

        if ($setting) {
            $withdrawalKycRequired = false;
            if (Schema::hasTable('kyc_configs')) {
                try {
                    $withdrawalKycRequired = (bool) optional(KycConfig::current())->isWithdrawalRequired();
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if (is_object($setting)) {
                $setting->withdrawal_kyc_required = $withdrawalKycRequired;
                $setting->asset_version = $assetVersion;
                $setting->settings_updated_at = $settingsUpdatedAt;
                $setting->custom_updated_at = $customUpdatedAt;

                try {
                    if (isset($setting->custom) && $setting->custom) {
                        $setting->custom->asset_version = $assetVersion;
                        $setting->custom->updated_at = $customUpdatedAt;

                        if (! empty($setting->custom->baixar_app_imagem)) {
                            $sep = str_contains($setting->custom->baixar_app_imagem, '?') ? '&' : '?';
                            $setting->custom->baixar_app_imagem .= $sep . 'v=' . ($contentTs ?: time());
                        }
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            } elseif (is_array($setting)) {
                $setting['withdrawal_kyc_required'] = $withdrawalKycRequired;
                $setting['asset_version'] = $assetVersion;
                $setting['settings_updated_at'] = $settingsUpdatedAt;
                $setting['custom_updated_at'] = $customUpdatedAt;
            }
        }

        $payload = ['setting' => $setting];
        $etag = '"' . md5($fingerprint . '|' . json_encode($payload, JSON_UNESCAPED_UNICODE)) . '"';
        $lastModified = $this->fingerprintToHttpDate($fingerprint);

        return [
            'fingerprint' => $fingerprint,
            'payload' => $payload,
            'etag' => $etag,
            'last_modified' => $lastModified,
        ];
    }

    private function currentFingerprint(): string
    {
        $settingsTs = $this->tableMaxUpdatedAtTimestamp('settings');
        $customTs = $this->tableMaxUpdatedAtTimestamp('custom_layouts');
        $kycTs = $this->tableMaxUpdatedAtTimestamp('kyc_configs');
        $assetV = (string) Cache::get('asset_version', 'v1');

        return "settings:{$settingsTs}|custom:{$customTs}|kyc:{$kycTs}|asset:{$assetV}";
    }

    private function tableMaxUpdatedAt(string $table): ?string
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'updated_at')) {
            return null;
        }

        try {
            $maxUpdated = DB::table($table)->max('updated_at');
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        if (! $maxUpdated) return null;
        if (is_string($maxUpdated)) return $maxUpdated;
        if (method_exists($maxUpdated, 'toDateTimeString')) return $maxUpdated->toDateTimeString();

        return (string) $maxUpdated;
    }

    private function tableMaxUpdatedAtTimestamp(string $table): int
    {
        $value = $this->tableMaxUpdatedAt($table);
        if (! $value) return 0;

        $ts = strtotime($value);
        return $ts !== false ? $ts : 0;
    }

    private function fallbackSettingPayload(): ?array
    {
        if (! Schema::hasTable('settings')) {
            return [
                'software_name' => config('app.name', 'PixFácil'),
                'asset_version' => (string) Cache::get('asset_version', 'v1'),
            ];
        }

        try {
            $row = DB::table('settings')->orderBy('id')->first();
            if (! $row) return null;

            $data = (array) $row;

            // Nunca devolve segredos de integração no fallback público.
            foreach (array_keys($data) as $key) {
                if (preg_match('/(?:secret|token|password|private|client_secret|api_key)/i', $key)) {
                    unset($data[$key]);
                }
            }

            return $data;
        } catch (\Throwable $e) {
            report($e);
            return ['software_name' => config('app.name', 'PixFácil')];
        }
    }

    private function fingerprintToHttpDate(string $fp): string
    {
        preg_match_all('/:(\d{6,})/', $fp, $matches);

        $timestamps = collect($matches[1] ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->values();

        $ts = $timestamps->max() ?: time();
        return gmdate('D, d M Y H:i:s', $ts) . ' GMT';
    }

    private function httpDateToTimestamp(?string $httpDate): int
    {
        if (! $httpDate) return 0;
        $ts = strtotime($httpDate);
        return $ts !== false ? $ts : 0;
    }

    private function clientCacheControl(): string
    {
        return 'no-store, no-cache, must-revalidate, max-age=0';
    }
}
