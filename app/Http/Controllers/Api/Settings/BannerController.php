<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    private const SERVER_CACHE_TTL = 3600;

    public function index(Request $request)
    {
        $cacheKey = 'api:banners:index:v1';
        $fingerprint = $this->currentFingerprint();
        $cached = Cache::get($cacheKey);

        if (! $cached || ($cached['fingerprint'] ?? null) !== $fingerprint) {
            $cached = $this->buildPayload($fingerprint);
            Cache::put($cacheKey, $cached, self::SERVER_CACHE_TTL);
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
    }

    public function bust()
    {
        Cache::forget('api:banners:index:v1');
        return response()->json(['ok' => true]);
    }

    private function buildPayload(string $fingerprint): array
    {
        $banners = Banner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get();

        $payload = [
            'banners' => $banners,
            'desktop' => $banners->where('show_desktop', true)->values(),
            'mobile' => $banners->where('show_mobile', true)->values(),
        ];

        return [
            'fingerprint' => $fingerprint,
            'payload' => $payload,
            'etag' => '"' . md5($fingerprint . '|' . json_encode($payload)) . '"',
            'last_modified' => $this->fingerprintToHttpDate($fingerprint),
        ];
    }

    private function currentFingerprint(): string
    {
        $row = DB::table('banners')
            ->selectRaw('MAX(updated_at) as max_updated, COUNT(*) as total')
            ->first();

        $ts = $row?->max_updated ? strtotime((string) $row->max_updated) : 0;
        return 'banners:' . $ts . ':' . (int) ($row?->total ?? 0);
    }

    private function fingerprintToHttpDate(string $fp): string
    {
        $parts = explode(':', $fp);
        $maybeTs = isset($parts[1]) ? (int) $parts[1] : 0;
        return gmdate('D, d M Y H:i:s', $maybeTs > 0 ? $maybeTs : time()) . ' GMT';
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
