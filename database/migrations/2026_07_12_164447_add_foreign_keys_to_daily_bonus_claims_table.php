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
        Schema::table('daily_bonus_claims', function (Blueprint $table) {
            $table->foreign(['user_id'], 'daily_bonus_claims_user_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_bonus_claims', function (Blueprint $table) {
            $table->dropForeign('daily_bonus_claims_user_fk');
        });
    }
};
