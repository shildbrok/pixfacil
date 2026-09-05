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
        Schema::table('affiliate_histories', function (Blueprint $table) {
            $table->foreign(['inviter'], 'affiliate_histories_inviter_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'affiliate_histories_user_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_histories', function (Blueprint $table) {
            $table->dropForeign('affiliate_histories_inviter_fk');
            $table->dropForeign('affiliate_histories_user_fk');
        });
    }
};
