<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciais do PIXUP.
 *
 * São QUATRO segredos distintos, e trocá-los entre si quebra tudo em silêncio:
 *   client_id + client_secret -> emitem o token OAuth2 (todas as rotas)
 *   signing_key               -> assina o HMAC das rotas financeiras (cashout)
 *   webhook_secret            -> valida o HMAC dos webhooks que CHEGAM
 *
 * signing_key != client_secret e webhook_secret != signing_key — são gerados
 * separadamente no dashboard do provedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->string('pixup_uri')->nullable();
            // 1000 = tamanho do valor CRIPTOGRAFADO, como os demais segredos.
            $table->string('pixup_client_id', 1000)->nullable();
            $table->string('pixup_client_secret', 1000)->nullable();
            $table->string('pixup_signing_key', 1000)->nullable();
            $table->string('pixup_webhook_secret', 1000)->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->tinyInteger('pixup_is_enable')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('gateways', fn (Blueprint $t) => $t->dropColumn([
            'pixup_uri', 'pixup_client_id', 'pixup_client_secret',
            'pixup_signing_key', 'pixup_webhook_secret',
        ]));

        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('pixup_is_enable'));
    }
};
