<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M-03 (rede de seguranca): apos remover o superadmin hardcoded (id === 1) do gate,
 * garante que EXISTA ao menos um usuario com a role 'admin'. Se nao houver nenhum,
 * concede a role ao usuario id=1 (o superadmin historico). Assim nenhum cliente que
 * dependia do id===1 fica trancado fora do painel.
 *
 * Idempotente e defensivo: nao faz nada se as tabelas nao existirem ou se ja houver admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'roles', 'model_has_roles'] as $t) {
            if (! Schema::hasTable($t)) {
                return;
            }
        }

        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if (! $adminRole) {
            return; // sem role admin definida, nada a fazer
        }

        $userModel = 'App\\Models\\User';

        // Ja existe algum admin? (considerando o model_type padrao do spatie)
        $hasAdmin = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_type', $userModel)
            ->exists();

        if ($hasAdmin) {
            return;
        }

        // Nao ha admin: concede ao usuario id=1, se existir.
        $userOne = DB::table('users')->where('id', 1)->first();
        if (! $userOne) {
            return;
        }

        DB::table('model_has_roles')->insert([
            'role_id'    => $adminRole->id,
            'model_type' => $userModel,
            'model_id'   => 1,
        ]);
    }

    public function down(): void
    {
        // Sem rollback: nao removemos concessao de admin por seguranca.
    }
};
