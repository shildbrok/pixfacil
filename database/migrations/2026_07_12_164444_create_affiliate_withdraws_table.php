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
        Schema::create('affiliate_withdraws', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_id')->nullable()->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 20)->default(0);
            $table->string('proof')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->string('pix_key')->nullable();
            $table->string('pix_type')->nullable();
            $table->string('type', 50)->nullable();
            $table->text('bank_info')->nullable();
            $table->string('currency', 50)->nullable();
            $table->string('symbol', 50)->nullable();
            $table->timestamps();
            $table->string('cpf')->nullable();
            $table->string('name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_withdraws');
    }
};
