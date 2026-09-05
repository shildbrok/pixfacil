<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base para múltiplos gateways de pagamento.
 *
 * 1) Coluna `gateway` em transactions/withdrawals: sem ela, o webhook de um
 *    provedor poderia casar com o payment_id de outro (improvável, mas é
 *    dinheiro real). Também permite trocar de gateway sem perder o histórico
 *    de qual provedor processou cada transação.
 * 2) Credenciais do DigitoPay na tabela `gateways` (mesmo padrão do GeraPix:
 *    linha única, segredo criptografado no model).
 * 3) Flag digitopay_is_enable em settings, espelhando gerapix_is_enable.
 *
 * A seleção de qual gateway está ativo usa settings.deposit_gateway e
 * settings.saque, que já existiam (semeados com 'gerapix') e nunca eram lidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway', 30)->nullable()->after('payment_method')->index();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('gateway', 30)->nullable()->after('type')->index();
        });

        // Tudo que existe hoje veio do GeraPix — carimba o histórico.
        DB::table('transactions')->whereNull('gateway')->update(['gateway' => 'gerapix']);
        DB::table('withdrawals')->whereNull('gateway')->update(['gateway' => 'gerapix']);

        Schema::table('gateways', function (Blueprint $table) {
            $table->string('digitopay_uri')->nullable();
            $table->string('digitopay_client_id', 1000)->nullable();
            // 1000 = tamanho do valor CRIPTOGRAFADO, como o gerapix_secret_token.
            $table->string('digitopay_secret', 1000)->nullable();
            // RO-3: allowlist de IPs de origem do webhook (lista separada por vírgula).
            $table->string('digitopay_webhook_ips', 500)->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->tinyInteger('digitopay_is_enable')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn('gateway'));
        Schema::table('withdrawals', fn (Blueprint $t) => $t->dropColumn('gateway'));

        Schema::table('gateways', fn (Blueprint $t) => $t->dropColumn([
            'digitopay_uri', 'digitopay_client_id', 'digitopay_secret', 'digitopay_webhook_ips',
        ]));

        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('digitopay_is_enable'));
    }
};
