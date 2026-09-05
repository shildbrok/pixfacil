<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a allowlist de IP do webhook do DigitoPay.
 *
 * Os gateways não têm IP de saída fixo — a lista quebraria a integração toda
 * vez que o provedor trocasse de IP, sem ganho de segurança real.
 *
 * O que protege o dinheiro NÃO é a allowlist: é nunca creditar pelo payload.
 * Todo webhook é reconsultado em getTransaction na fonte, e só credita se a
 * própria API confirmar REALIZADO no valor que consta na nossa base. Um webhook
 * forjado não passa por isso: exigiria um id/idempotencyKey (UUID) real e uma
 * transação de fato paga — e a trava status 0->1 ainda impede replay.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gateways', 'digitopay_webhook_ips')) {
            Schema::table('gateways', function (Blueprint $table) {
                $table->dropColumn('digitopay_webhook_ips');
            });
        }
    }

    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->string('digitopay_webhook_ips', 500)->nullable();
        });
    }
};
