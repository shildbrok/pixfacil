<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Tabela GENÉRICA de log cru de webhook, com coluna `gateway`.
 *    Substitui a digitopay_webhook_logs: já são 3 provedores sem assinatura de
 *    webhook (DigitoPay, PodPay, ForceOnePay) e uma tabela por gateway não se
 *    sustenta. Guardar o payload cru é trilha de auditoria — obrigatório num
 *    sistema de dinheiro — e é o que permite reprocessar à mão.
 *
 * 2) Credenciais do PodPay. A api_key (sk_live_/sk_test_) é o único segredo;
 *    a base URL define produção x sandbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->index();
            $table->string('channel', 20)->nullable();   // cashin | cashout
            $table->string('provider_id')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Move o histórico do DigitoPay, se houver, e aposenta a tabela antiga.
        if (Schema::hasTable('digitopay_webhook_logs')) {
            foreach (DB::table('digitopay_webhook_logs')->orderBy('id')->cursor() as $row) {
                DB::table('gateway_webhook_logs')->insert([
                    'gateway'      => 'digitopay',
                    'channel'      => $row->channel,
                    'provider_id'  => $row->provider_id,
                    'external_id'  => $row->idempotency_key,
                    'ip'           => $row->ip,
                    'payload'      => $row->payload,
                    'processed_at' => $row->processed_at,
                    'error'        => $row->error,
                    'created_at'   => $row->created_at,
                    'updated_at'   => $row->updated_at,
                ]);
            }

            Schema::dropIfExists('digitopay_webhook_logs');
        }

        Schema::table('gateways', function (Blueprint $table) {
            $table->string('podpay_uri')->nullable();
            // 1000 = tamanho do valor CRIPTOGRAFADO.
            $table->string('podpay_api_key', 1000)->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->tinyInteger('podpay_is_enable')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_webhook_logs');

        Schema::table('gateways', fn (Blueprint $t) => $t->dropColumn(['podpay_uri', 'podpay_api_key']));
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('podpay_is_enable'));
    }
};
