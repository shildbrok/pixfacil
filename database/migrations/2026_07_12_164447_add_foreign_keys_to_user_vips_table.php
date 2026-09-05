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
        Schema::table('user_vips', function (Blueprint $table) {
            $table->foreign(['user_id'], 'user_vips_user_fk')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['vip_id'], 'user_vips_vip_fk')->references(['id'])->on('vips')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_vips', function (Blueprint $table) {
            $table->dropForeign('user_vips_user_fk');
            $table->dropForeign('user_vips_vip_fk');
        });
    }
};
