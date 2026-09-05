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
        Schema::create('wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('currency', 20);
            $table->string('symbol', 5);
            $table->decimal('balance', 20)->default(0);
            $table->decimal('balance_bonus_rollover', 10)->nullable()->default(0);
            $table->decimal('balance_deposit_rollover', 10)->nullable()->default(0);
            $table->decimal('balance_withdrawal', 10)->nullable()->default(0);
            $table->decimal('balance_bonus', 20)->default(0);
            $table->decimal('balance_cryptocurrency', 20, 8)->default(0);
            $table->decimal('balance_demo', 20, 8)->nullable()->default(1000);
            $table->decimal('refer_rewards', 20)->default(0);
            $table->boolean('hide_balance')->default(false);
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->decimal('total_bet', 20)->default(0);
            $table->bigInteger('total_won')->default(0);
            $table->bigInteger('total_lose')->default(0);
            $table->bigInteger('last_won')->default(0);
            $table->bigInteger('last_lose')->default(0);
            $table->bigInteger('vip_level')->nullable()->default(0);
            $table->bigInteger('vip_points')->nullable()->default(0);
            $table->timestamp('welcome_bonus_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
