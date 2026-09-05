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
        Schema::create('deposits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_id')->nullable()->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 20)->default(0);
            $table->string('type');
            $table->string('proof')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->string('currency', 50)->nullable();
            $table->string('symbol', 50)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'deposits_status_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
