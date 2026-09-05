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
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreign(['game_id'], 'game_sessions_game_fk')->references(['id'])->on('games')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['user_id'], 'game_sessions_user_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropForeign('game_sessions_game_fk');
            $table->dropForeign('game_sessions_user_fk');
        });
    }
};
