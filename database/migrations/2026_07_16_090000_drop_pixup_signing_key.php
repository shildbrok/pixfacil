<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o pixup_signing_key.
 *
 * Ele não existe. A doc oficial (dev.pixupbr.com) não pede assinatura HMAC em
 * nenhuma rota financeira — só o Bearer OAuth2 — e o campo também não aparece no
 * painel de contas reais. Foi implementado a partir de uma documentação de
 * terceiros que descrevia um "signing_key" inexistente.
 *
 * O webhook_secret continua: esse é real e vem do dashboard da PIXUP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gateways', 'pixup_signing_key')) {
            Schema::table('gateways', function (Blueprint $table) {
                $table->dropColumn('pixup_signing_key');
            });
        }
    }

    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->string('pixup_signing_key', 1000)->nullable();
        });
    }
};
