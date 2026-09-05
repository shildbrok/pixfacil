<?php



namespace App\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CacheNuker
{
    public function run(array $options = []): array
    {
        $deep          = (bool)($options['deep'] ?? true);
        $clearSessions = (bool)($options['sessions'] ?? true);
        $clearQueues   = (bool)($options['queues'] ?? true);

        $report = [
            'env' => [
                'cache_driver'   => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_driver'   => config('queue.default'),
            ],
            'artisan'            => [],
            'cache_fixed_forget' => 0,
            'cache_pattern_del'  => 0,
            'cache_files_del'    => 0,
            'view_files_del'     => 0,
            'bootstrap_del'      => 0,
            'session_del'        => 0,
            'queue_del'          => 0,
            'tags_flushed'       => false,
            'asset_version'      => null,
            'opcache_reset'      => false,
        ];


        $report['artisan'][] = Artisan::call('optimize:clear');
        $report['artisan'][] = Artisan::call('event:clear');


        $this->clearAppCaches($report);


        if ($deep) {
            $report['view_files_del']  += $this->purgeDir(storage_path('framework/views'));
            $report['cache_files_del'] += $this->purgeDir(storage_path('framework/cache/data'));
            $report['bootstrap_del']   += $this->purgeDir(base_path('bootstrap/cache'), ['.gitignore']);
        }


        if ($clearSessions) {
            $report['session_del'] += $this->clearSessionsByDriver();
        }


        if ($clearQueues) {
            $report['queue_del'] += $this->clearQueuesByDriver();
        }


        $assetVersion = (string) Str::uuid();
        Cache::forever('asset_version', $assetVersion);
        $report['asset_version'] = $assetVersion;


        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $report['opcache_reset'] = true;
        }

        return $report;
    }

    private function clearAppCaches(array &$report): void
    {

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['providers', 'categories', 'games', 'missions'])->flush();
            $report['tags_flushed'] = true;
        }


        $fixed = [
            'pf:v1:providers_with_games',
            'pf:v2:providers_with_games_min',
            'pf:v1:games_by_categories',
            'pf:v1:featured_games',
            'pf:v1:categories_index_v1',
            'pf:v1:missions_index_v1',
        ];
        foreach ($fixed as $k) {
            if (Cache::forget($k)) {
                $report['cache_fixed_forget']++;
            }
        }


        if (config('cache.default') === 'redis') {
            if ($redis = $this->getRedisConnectionForCache()) {
                foreach (['pf:v1:all_games:*', 'pf:v*:all_games:*', 'pf:v1:*', 'pf:v2:*'] as $pat) {
                    $report['cache_pattern_del'] += $this->redisDeleteByPattern($redis, $pat);
                }
            }
        }
    }

    private function clearSessionsByDriver(): int
    {
        $driver  = config('session.driver');
        $deleted = 0;

        if ($driver === 'file') {
            return $this->purgeDir(storage_path('framework/sessions'));
        }

        if ($driver === 'redis') {
            $connName = config('session.connection') ?: 'default';
            $cookie   = config('session.cookie', 'laravel_session');
            $global   = config('database.redis.options.prefix', '');
            $patterns = [
                "{$global}{$cookie}:*",
                "{$cookie}:*",
                "{$global}sessions:*",
                "sessions:*",
            ];

            $redis = Redis::connection($connName);
            foreach ($patterns as $pat) {
                $deleted += $this->redisDeleteByPattern($redis, $pat);
            }
        }

        return $deleted;
    }

    private function clearQueuesByDriver(): int
    {
        $driver  = config('queue.default');
        $deleted = 0;

        if ($driver === 'sync') {
            return 0;
        }

        if ($driver === 'redis') {
            $connName = config('queue.connections.redis.connection', 'default');
            $queue    = config('queue.connections.redis.queue', 'default');
            $prefix   = config('database.redis.options.prefix', '');

            $patterns = [
                "{$prefix}queues:{$queue}*",
                "{$prefix}queues:*",
                "queues:{$queue}*",
                "queues:*",
            ];

            $redis = Redis::connection($connName);
            foreach ($patterns as $pat) {
                $deleted += $this->redisDeleteByPattern($redis, $pat);
            }
        }

        return $deleted;
    }

    private function purgeDir(string $dir, array $keep = []): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $deleted = 0;
        foreach (File::allFiles($dir) as $file) {
            if (in_array($file->getFilename(), $keep, true)) {
                continue;
            }
            try {
                File::delete($file->getRealPath());
                $deleted++;
            } catch (\Throwable $e) {
            }
        }
        return $deleted;
    }

    private function getRedisConnectionForCache()
    {
        $store  = config('cache.default');
        $stores = config('cache.stores');
        if (!isset($stores[$store])) {
            return null;
        }
        $conf = $stores[$store];
        if (($conf['driver'] ?? null) !== 'redis') {
            return null;
        }

        $connectionName = $conf['connection'] ?? 'default';
        try {
            return Redis::connection($connectionName);
        } catch (\Throwable $e) {
            return null;
        }
    }


    private function redisDeleteByPattern($redis, string $pattern, int $scanCount = 1000, int $chunkSize = 500): int
    {
        $deleted = 0;
        $cursor  = 0;

        do {
            $keys = [];

            try {

                $result = $redis->scan($cursor, ['match' => $pattern, 'count' => $scanCount]);
                if ($result !== false && is_array($result)) {
                    $keys = $result;
                }
            } catch (\Throwable $e) {
                try {

                    $result = $redis->scan($cursor, $pattern, $scanCount);
                    if ($result !== false && is_array($result)) {
                        $keys = $result;
                    }
                } catch (\Throwable $e2) {

                    return $deleted;
                }
            }

            if (!empty($keys)) {
                foreach (array_chunk($keys, $chunkSize) as $chunk) {
                    try {
                        $redis->del($chunk);
                        $deleted += count($chunk);
                    } catch (\Throwable $e) {

                    }
                }
            }

        } while ((int) $cursor !== 0);

        return $deleted;
    }
}
