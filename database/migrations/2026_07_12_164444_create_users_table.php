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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('admin_action_pin', 255)->nullable();
            $table->rememberToken();
            $table->string('session_token')->nullable();
            $table->timestamps();
            $table->string('avatar')->nullable()->default('uploads/avatar/padrao.webp');
            $table->string('cpf', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->tinyInteger('banned')->default(0);
            $table->unsignedBigInteger('inviter')->nullable()->index();
            $table->string('inviter_code', 25)->nullable();
            $table->decimal('affiliate_cpa', 20)->default(0);
            $table->decimal('affiliate_baseline', 20)->default(0);
            $table->string('status', 50)->default('active');
            $table->boolean('is_influencer')->default(false)->index();
            $table->string('language')->default('pt_BR');
            $table->integer('role_id')->nullable()->default(3);
            $table->string('betcrm_ref', 64)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
