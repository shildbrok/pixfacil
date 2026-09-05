<?php



namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [

    ];

    public function boot(): void
    {
        $this->registerPolicies();



        Gate::define('admin', function ($user) {
            if (! $user) {
                return false;
            }

            // M-03: acesso admin agora e SOMENTE via role 'admin'. Removido o superadmin
            // hardcoded (id === 1), que concedia acesso total fora do modelo de roles.
            // A migration 2026_07_12_170500 garante que exista ao menos um admin.
            return method_exists($user, 'hasRole') && $user->hasRole('admin');
        });
    }
}
