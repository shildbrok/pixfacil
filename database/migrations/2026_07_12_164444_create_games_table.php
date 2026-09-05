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
        Schema::create('games', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('provider_id')->index();
            $table->string('game_server_url')->nullable()->default('inativo');
            $table->string('game_id');
            $table->string('game_name');
            $table->string('game_code')->index();
            $table->string('game_type')->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('cover')->nullable();
            $table->string('status')->default('0');
            $table->string('technology')->nullable()->default('html5');
            $table->tinyInteger('has_lobby')->default(0);
            $table->tinyInteger('is_mobile')->default(0);
            $table->tinyInteger('has_freespins')->default(0);
            $table->tinyInteger('has_tables')->default(0);
            $table->tinyInteger('only_demo')->nullable()->default(0);
            $table->bigInteger('rtp')->default(0);
            $table->string('distribution')->default('play_fiver');
            $table->bigInteger('views')->default(0);
            $table->tinyInteger('is_featured')->nullable()->default(0);
            $table->tinyInteger('show_home')->nullable()->default(1);
            $table->timestamp('created_at')->nullable()->index('idx_games_created_at');
            $table->timestamp('updated_at')->nullable();
            $table->boolean('original')->default(false);

            $table->index(['provider_id', 'status'], 'idx_games_provider_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
