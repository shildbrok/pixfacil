<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_id', 100)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('payment_method')->nullable();
            $table->decimal('price', 20)->default(0);
            $table->string('currency', 20)->default('usd');
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('fbp', 255)->nullable();
            $table->string('fbc', 255)->nullable();
            $table->string('source_url', 255)->nullable();
            $table->tinyInteger('status')->nullable()->default(0);
            $table->timestamp('created_at')->nullable()->index('transactions_created_at_idx');
            $table->timestamp('updated_at')->nullable();
            $table->string('idUnico')->nullable()->unique();
            $table->string('gateway_status', 50)->nullable();
            $table->text('pix_copia_e_cola')->nullable();
            $table->mediumText('qr_code_base64')->nullable();
            $table->longText('gateway_response')->nullable();
            $table->dateTime('qr_received_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
