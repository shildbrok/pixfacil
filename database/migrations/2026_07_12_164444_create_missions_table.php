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
        Schema::create('missions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['deposit', 'game_bet', 'total_bet', 'rounds_played', 'win_amount', 'loss_amount']);
            $table->string('game_id', 50)->nullable();
            $table->decimal('target_amount', 10);
            $table->decimal('reward', 10);
            $table->string('image', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->nullable()->default('active');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
