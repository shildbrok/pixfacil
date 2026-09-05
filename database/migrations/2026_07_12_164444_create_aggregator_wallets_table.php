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
        Schema::create('aggregator_wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider')->default('playfiver')->index();
            $table->string('wallet_key')->unique();
            $table->string('name');
            $table->decimal('balance', 14)->default(0);
            $table->string('currency', 8)->default('BRL');
            $table->boolean('is_primary')->default(false);
            $table->boolean('info_only')->default(false);
            $table->boolean('notify_enabled')->default(false);
            $table->string('notify_email')->nullable();
            $table->decimal('notify_threshold', 14)->nullable();
            $table->boolean('was_below_notify_threshold')->default(false);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aggregator_wallets');
    }
};
