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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_id', 255)->nullable()->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 20)->default(0);
            $table->string('type')->nullable();
            $table->string('proof')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->string('pix_key')->nullable();
            $table->string('pix_type')->nullable();
            $table->text('bank_info')->nullable();
            $table->string('currency', 50)->nullable();
            $table->string('symbol', 50)->nullable();
            $table->timestamp('created_at')->nullable()->index('withdrawals_created_at_idx');
            $table->timestamp('updated_at')->nullable();
            $table->string('cpf')->nullable();
            $table->string('name')->nullable();

            $table->index(['status', 'created_at'], 'withdrawals_status_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
