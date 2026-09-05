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
        Schema::create('custom_layouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->string('link_suporte', 100)->nullable();
            $table->string('banner_registro', 100)->nullable();
            $table->string('banner_login', 100)->nullable();
            $table->string('link_app', 100)->nullable();
            $table->string('link_telegram', 100)->nullable();
            $table->string('link_facebook', 100)->nullable();
            $table->string('link_whatsapp', 100)->nullable();
            $table->string('link_instagram', 100)->nullable();
            $table->string('link_lincenca', 100)->nullable();
            $table->string('footer_imagen1', 100)->nullable();
            $table->string('footer_imagen2', 100)->nullable();
            $table->string('footer_imagen3', 100)->nullable();
            $table->string('footer_imagen4', 255)->nullable();
            $table->string('token_jivochat', 255)->nullable();
            $table->string('maiores_ganhos_status', 25)->nullable();
            $table->string('live_ganhos_status', 25)->nullable();
            $table->string('baixar_app_imagem', 90)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_layouts');
    }
};
