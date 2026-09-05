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
        Schema::create('betcrm_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('enabled')->default(false);
            $table->string('base_url', 255)->default('https://betcrm.io/api/v1');
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable();
            $table->boolean('send_registration')->default(true);
            $table->boolean('send_login')->default(true);
            $table->boolean('send_app_install')->default(true);
            $table->boolean('send_deposit')->default(true);
            $table->boolean('send_withdrawal')->default(true);
            $table->timestamps();
            $table->string('callback_ips', 255)->nullable()->default('140.99.164.26');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('betcrm_settings');
    }
};
