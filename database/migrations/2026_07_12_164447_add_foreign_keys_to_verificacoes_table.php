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
        Schema::table('verificacoes', function (Blueprint $table) {
            $table->foreign(['aprovado_por'], 'verificacoes_aprovador_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['user_id'], 'verificacoes_user_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verificacoes', function (Blueprint $table) {
            $table->dropForeign('verificacoes_aprovador_fk');
            $table->dropForeign('verificacoes_user_fk');
        });
    }
};
