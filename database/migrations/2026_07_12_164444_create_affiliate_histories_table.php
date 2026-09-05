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
        Schema::create('affiliate_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('inviter')->index();
            $table->decimal('commission', 20)->default(0);
            $table->string('commission_type')->nullable();
            $table->tinyInteger('deposited')->nullable()->default(0);
            $table->decimal('deposited_amount', 10)->nullable()->default(0);
            $table->bigInteger('losses')->nullable()->default(0);
            $table->decimal('losses_amount', 10)->nullable()->default(0);
            $table->decimal('commission_paid', 10)->nullable()->default(0);
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_histories');
    }
};
