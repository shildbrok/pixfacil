<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B-02 (passo seguro): as 40 permissoes existiam mas role_has_permissions estava vazia
 * (RBAC so andaime). Esta migration vincula TODAS as permissoes a role 'admin', deixando
 * o RBAC coerente. NAO muda comportamento (o admin ja tem acesso total via hasRole).
 *
 * E o pre-requisito para, no futuro, criar papeis LIMITADos (ex.: 'suporte' com um
 * subconjunto) — isso exigira checagens por permissao nas paginas Filament, que e um
 * trabalho a parte (hoje as 53 paginas checam hasRole('admin')).
 *
 * Idempotente e defensivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'permissions', 'role_has_permissions'] as $t) {
            if (! Schema::hasTable($t)) {
                return;
            }
        }

        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if (! $adminRole) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id');
        if ($permissionIds->isEmpty()) {
            return;
        }

        $existing = DB::table('role_has_permissions')
            ->where('role_id', $adminRole->id)
            ->pluck('permission_id')
            ->flip();

        $rows = [];
        foreach ($permissionIds as $pid) {
            if (! isset($existing[$pid])) {
                $rows[] = ['permission_id' => $pid, 'role_id' => $adminRole->id];
            }
        }

        if ($rows !== []) {
            DB::table('role_has_permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        // Sem rollback destrutivo do vinculo de permissoes.
    }
};
