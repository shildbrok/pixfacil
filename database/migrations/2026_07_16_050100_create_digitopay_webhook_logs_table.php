<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log cru de TODO webhook do DigitoPay, inclusive os descartados.
 *
 * O DigitoPay não assina o webhook e permite reprocessar em lote — então o
 * mesmo evento chega mais de uma vez, e um payload sozinho não prova nada.
 * Guardar o cru é o que dá trilha de auditoria e permite reprocessar à mão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digitopay_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);                 // cashin | cashout
            $table->string('provider_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digitopay_webhook_logs');
    }
};
