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
        Schema::create('configs_rounds_free', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('spins');
            $table->string('game_code')->index('idx_game_code');
            $table->decimal('value', 10);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'value'], 'idx_status_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configs_rounds_free');
    }
};
