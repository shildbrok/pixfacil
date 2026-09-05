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
        Schema::create('verificacoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('nome_completo');
            $table->string('cpf', 20);
            $table->string('selfie_path', 255)->nullable();
            $table->string('doc_frente_path', 255)->nullable();
            $table->string('doc_verso_path', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('observacao_rejeicao')->nullable();
            $table->unsignedBigInteger('aprovado_por')->nullable()->index();
            $table->timestamp('aprovado_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verificacoes');
    }
};
