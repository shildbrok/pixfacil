<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciais do AbilityPay.
 *
 * Autenticação por par X-Client-Id + X-Client-Secret (sem token). O client_id
 * também recebe tratamento de segredo: junto com o secret, ele move dinheiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->string('abilitypay_uri')->nullable();
            // 1000 = tamanho do valor CRIPTOGRAFADO.
            $table->string('abilitypay_client_id', 1000)->nullable();
            $table->string('abilitypay_client_secret', 1000)->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->tinyInteger('abilitypay_is_enable')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('gateways', fn (Blueprint $t) => $t->dropColumn([
            'abilitypay_uri', 'abilitypay_client_id', 'abilitypay_client_secret',
        ]));

        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('abilitypay_is_enable'));
    }
};
