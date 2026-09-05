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

    private const CLIENT_MAX_AGE_SECONDS = 300; 
    private const SERVER_CACHE_TTL       = 3600; 


    public function index(Request $request)
    {
        $cacheKey    = 'api:banners:index:v1';
        $fingerprint = $this->currentFingerprint(); 


        $cached = Cache::get($cacheKey);


        if (!$cached || ($cached['fingerprint'] ?? null) !== $fingerprint) {


            $lock = method_exists(Cache::getStore(), 'lock')
                ? Cache::lock('lock:' . $cacheKey, 5)
                : null;

            try {
                if ($lock && $lock->get()) {

                    $cached = Cache::get($cacheKey);

                    if (!$cached || ($cached['fingerprint'] ?? null) !== $fingerprint) {
                        $banners = Banner::get();

                        $payload = ['banners' => $banners];


                        $etag = '"' . md5($fingerprint . '|' . json_encode($payload)) . '"';


                        $lastModified = $this->fingerprintToHttpDate($fingerprint);

                        $cached = [
                            'fingerprint'   => $fingerprint,
                            'payload'       => $payload,
                            'etag'          => $etag,
                            'last_modified' => $lastModified,
                        ];

                        Cache::put($cacheKey, $cached, self::SERVER_CACHE_TTL);
                    }
                } else {

                    $banners = Banner::get();
                    $payload = ['banners' => $banners];

                    $cached = [
                        'fingerprint'   => $fingerprint,
                        'payload'       => $payload,
                        'etag'          => '"' . md5($fingerprint . '|' . json_encode($payload)) . '"',
                        'last_modified' => $this->fingerprintToHttpDate($fingerprint),
                    ];

                    Cache::put($cacheKey, $cached, self::SERVER_CACHE_TTL);
                }
            } finally {
                if (isset($lock) && $lock) {
                    optional($lock)->release();
                }
            }
        }


        $ifNoneMatch     = $request->header('If-None-Match');
        $ifModifiedSince = $request->header('If-Modified-Since');


        if ($ifNoneMatch && trim($ifNoneMatch) === $cached['etag']) {
            return response('', 304)
                ->header('ETag', $cached['etag'])
                ->header('Last-Modified', $cached['last_modified'])
                ->header('Cache-Control', $this->clientCacheControl());
        }


        if (
            $ifModifiedSince &&
            $this->httpDateToTimestamp($ifModifiedSince) >= $this->httpDateToTimestamp($cached['last_modified'])
        ) {
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




    private function currentFingerprint(): string
    {
        $maxUpdated = DB::table('banners')->max('updated_at'); 

        if ($maxUpdated) {
            $ts = is_string($maxUpdated)
                ? strtotime($maxUpdated)
                : $maxUpdated->getTimestamp();

            return 'banners:' . $ts;
        }


        return 'banners:no-updated:' . now()->format('Ymd');
    }


    private function fingerprintToHttpDate(string $fp): string
    {

        if (Str::contains($fp, ':')) {
            [, $number] = explode(':', $fp, 2);
            $maybeTs = (int) $number;
            if ($maybeTs > 0) {
                return gmdate('D, d M Y H:i:s', $maybeTs) . ' GMT';
            }
        }

        return gmdate('D, d M Y H:i:s') . ' GMT';
    }


    private function httpDateToTimestamp(?string $httpDate): int
    {
        if (!$httpDate) {
            return 0;
        }

        $ts = strtotime($httpDate);
        return $ts !== false ? $ts : 0;
    }


    private function clientCacheControl(): string
    {



        return 'no-store, no-cache, must-revalidate, max-age=0';
    }


}