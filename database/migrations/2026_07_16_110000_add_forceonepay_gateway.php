<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciais do ForceOnePay.
 *
 * Só um segredo: o token contratado, usado para emitir o Bearer de trabalho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->string('forceonepay_uri')->nullable();
            // 1000 = tamanho do valor CRIPTOGRAFADO.
            $table->string('forceonepay_token', 1000)->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->tinyInteger('forceonepay_is_enable')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('gateways', fn (Blueprint $t) => $t->dropColumn(['forceonepay_uri', 'forceonepay_token']));
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('forceonepay_is_enable'));
    }
};
