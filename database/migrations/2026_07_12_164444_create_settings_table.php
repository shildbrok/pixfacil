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
        Schema::create('settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 255)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->text('og_description')->nullable();
            $table->string('twitter_title', 255)->nullable();
            $table->text('twitter_description')->nullable();
            $table->tinyInteger('allow_indexing')->default(0);
            $table->string('site_url', 255)->nullable();
            $table->string('software_name', 255)->nullable();
            $table->string('software_favicon', 255)->nullable();
            $table->string('software_logo_white', 255)->nullable();
            $table->string('software_logo_black', 255)->nullable();
            $table->bigInteger('initial_bonus')->nullable()->default(0);
            $table->decimal('min_deposit', 10)->nullable()->default(20);
            $table->decimal('max_deposit', 10)->nullable()->default(0);
            $table->decimal('min_withdrawal', 10)->nullable()->default(20);
            $table->decimal('max_withdrawal', 10)->nullable()->default(0);
            $table->bigInteger('rollover')->nullable()->default(10);
            $table->bigInteger('rollover_deposit')->nullable()->default(1);
            $table->boolean('revshare_reverse')->nullable()->default(true);
            $table->bigInteger('withdrawal_limit')->nullable();
            $table->string('withdrawal_period', 30)->nullable();
            $table->boolean('withdrawal_auto_approve')->default(false);
            $table->decimal('withdrawal_auto_approve_max', 10)->default(0);
            $table->string('saque')->default('ezzepay');
            $table->decimal('cpa_baseline', 10)->default(0);
            $table->decimal('cpa_value', 10)->default(0);
            $table->boolean('disable_rollover')->nullable()->default(false);
            $table->tinyInteger('gerapix_is_enable')->default(0);
            $table->string('deposit_gateway')->default('ondapay');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
