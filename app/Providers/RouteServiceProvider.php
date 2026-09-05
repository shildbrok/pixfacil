<?php



namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{

    public const HOME = '/';


    public function boot(): void
    {

        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }


    protected function configureRateLimiting(): void
    {

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(240)->by(
                $request->user()?->id ?: $request->ip()
            );
        });


        RateLimiter::for('auth', function (Request $request) {
            $key = $request->ip();

            return [
                Limit::perMinute(20)->by($key), 
                Limit::perHour(200)->by($key), 
            ];
        });


        RateLimiter::for('wallet', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [

                Limit::perMinute(30)->by($key), 
                Limit::perHour(120)->by($key), 
            ];
        });


        RateLimiter::for('games', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(180)->by($key), 
            ];
        });


        RateLimiter::for('affiliate', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });


        RateLimiter::for('public-short', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });


        RateLimiter::for('settings', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });


        RateLimiter::for('kyc', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(5)->by($key),
                Limit::perHour(20)->by($key),
            ];
        });


        RateLimiter::for('webhooks', function (Request $request) {

            return [
                Limit::perMinute(6000)->by('webhooks-global'),
            ];
        });
    }
}
