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
        Schema::table('mission_users', function (Blueprint $table) {
            $table->foreign(['mission_id'], 'mission_users_mission_fk')->references(['id'])->on('missions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'mission_users_user_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mission_users', function (Blueprint $table) {
            $table->dropForeign('mission_users_mission_fk');
            $table->dropForeign('mission_users_user_fk');
        });
    }
};
