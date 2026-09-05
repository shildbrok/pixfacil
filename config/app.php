<?php



use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [



    'name' => env('APP_NAME', 'Laravel'),



    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Flags de infraestrutura opcional (ver .env, secao 12)
    |--------------------------------------------------------------------------
    | Expostas como config para funcionarem mesmo com `php artisan config:cache`
    | (env() fora de arquivos de config retorna null quando o config esta cacheado).
    | Leia SEMPRE via config('app.redis_enabled') / config('app.cloudflare_enabled').
    */
    'redis_enabled' => env('REDIS_ENABLED', false),
    'cloudflare_enabled' => env('CLOUDFLARE_ENABLED', false),



    'debug' => (bool) env('APP_DEBUG', false),



    'url' => env('APP_URL', 'http://localhost'),


    'trusted_proxies' => env('TRUSTED_PROXIES', ''),


    'demo' => env('APP_DEMO', false),

    'filament_base_url' => env('FILAMENT_BASE_URL', 'admin'),

    'asset_url' => env('ASSET_URL'),



    'timezone' => 'America/Sao_Paulo',



    'locale' => 'pt_BR',



    'fallback_locale' => 'en',



    'faker_locale' => 'en_US',



    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',



    'maintenance' => [
        'driver' => 'file',

    ],



    'providers' => ServiceProvider::defaultProviders()->merge([

        Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
        Spatie\Permission\PermissionServiceProvider::class,

        \App\Providers\FilamentServiceProvider::class,

  
        App\Providers\FilamentThemeServiceProvider::class,

        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\Filament\AdminPanelProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),



    'aliases' => Facade::defaultAliases()->merge([

        'Helper' => \App\Helpers\Core::class,
    ])->toArray(),

];
