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
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('session_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index('idx_orders_tx');
            $table->string('game');
            $table->string('game_uuid');
            $table->string('type', 50);
            $table->string('type_money', 50);
            $table->decimal('amount', 20)->default(0);
            $table->string('providers');
            $table->tinyInteger('refunded')->default(0);
            $table->tinyInteger('status')->default(0);
            $table->string('round_id', 255)->nullable()->index();
            $table->timestamp('created_at')->nullable()->index('orders_created_at_idx');
            $table->timestamp('updated_at')->nullable();

            $table->index(['type', 'created_at'], 'orders_type_created_idx');
            $table->unique(['providers', 'transaction_id', 'type'], 'orders_unique_provider_tx_type');
            $table->index(['user_id', 'created_at'], 'orders_user_created_at_idx');
            $table->index(['user_id', 'type'], 'orders_user_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
