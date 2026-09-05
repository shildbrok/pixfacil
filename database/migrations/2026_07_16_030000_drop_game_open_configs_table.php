<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a trava que exigia depósito pago no dia para o usuário abrir jogos.
 * A regra foi descontinuada: a tabela guardava um único flag global
 * (requires_deposit_today), que já estava desligado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('game_open_configs');
    }

    public function down(): void
    {
        // Recria a estrutura original. O flag volta desligado, que era o estado
        // em produção quando a regra foi removida.
        Schema::create('game_open_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('requires_deposit_today')->default(false);
            $table->timestamps();
        });
    }
};
