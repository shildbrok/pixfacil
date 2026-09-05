<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha a corrida no resgate de recompensa VIP (VipController::claim).
 *
 * `UserVip::firstOrCreate(['user_id','vip_id'])` sem índice UNIQUE permitia que
 * requisições concorrentes criassem várias linhas para o mesmo par, cada uma com
 * `last_reward_claimed_at = null`, e cada transação travava/relia a SUA própria
 * linha — furando a idempotência e creditando a recompensa N vezes.
 *
 * Com o UNIQUE, o `firstOrCreate` (Laravel 10.20+, via createOrFirst) converge
 * todas as requisições para a MESMA linha; o lockForUpdate na transação passa a
 * serializar de verdade. Espelha o unique já existente em `mission_users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Defensivo: colapsa eventuais duplicatas pré-existentes (mantém o menor id)
        // para que a criação do índice não falhe. Hoje a tabela está vazia.
        $duplicates = DB::table('user_vips')
            ->select('user_id', 'vip_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('user_id', 'vip_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('user_vips')
                ->where('user_id', $dup->user_id)
                ->where('vip_id', $dup->vip_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('user_vips', function (Blueprint $table) {
            $table->unique(['user_id', 'vip_id'], 'user_vips_user_vip_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_vips', function (Blueprint $table) {
            $table->dropUnique('user_vips_user_vip_unique');
        });
    }
};
