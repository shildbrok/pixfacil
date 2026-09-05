<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Semeia o andaime de RBAC (spatie/laravel-permission) exigido pelo painel admin.
 *
 * As migrations criam as tabelas roles/permissions VAZIAS — sem este seeder um
 * `git clone` novo fica sem a role 'admin' e sem permissões, e ninguém consegue
 * acessar o Filament (canAccessPanel exige hasRole('admin')).
 *
 * Idempotente: usa firstOrCreate/syncPermissions, pode rodar quantas vezes quiser.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** As 40 permissões (guard web) usadas pelo RBAC do admin. */
    private const PERMISSIONS = [
        'games-exclusive-edit', 'games-exclusive-view', 'games-exclusive-create',
        'admin-view', 'admin-create', 'admin-edit', 'admin-delete',
        'category-view', 'category-create', 'category-edit', 'category-delete',
        'game-view', 'game-create', 'game-edit', 'game-delete',
        'wallet-view', 'wallet-create', 'wallet-edit', 'wallet-delete',
        'deposit-view', 'deposit-create', 'deposit-edit', 'deposit-update', 'deposit-delete',
        'withdrawal-view', 'withdrawal-create', 'withdrawal-edit', 'withdrawal-update', 'withdrawal-delete',
        'order-view', 'order-create', 'order-edit', 'order-update', 'order-delete',
        'admin-menu-view',
        'setting-view', 'setting-create', 'setting-edit', 'setting-update', 'setting-delete',
    ];

    public function run(): void
    {
        $guard = 'web';

        // Limpa o cache de permissões antes de mexer (evita estado inconsistente).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) Permissões
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // 2) Roles — 'admin' criada primeiro para receber id=1 num banco novo.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'afiliado', 'guard_name' => $guard]);

        // 3) Admin recebe TODAS as permissões.
        $admin->syncPermissions(Permission::where('guard_name', $guard)->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'RBAC: %d permissões, roles admin/afiliado, admin com %d permissões.',
            DB::table('permissions')->count(),
            $admin->permissions()->count()
        ));
    }
}
