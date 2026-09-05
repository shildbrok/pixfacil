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
        Schema::table('category_game', function (Blueprint $table) {
            $table->foreign(['category_id'], 'category_game_category_fk')->references(['id'])->on('categories')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['game_id'], 'category_game_game_fk')->references(['id'])->on('games')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_game', function (Blueprint $table) {
            $table->dropForeign('category_game_category_fk');
            $table->dropForeign('category_game_game_fk');
        });
    }
};
