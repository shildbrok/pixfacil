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
        Schema::create('distribution_systems', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('meta_arrecadacao')->default(0);
            $table->integer('percentual_distribuicao')->default(0);
            $table->integer('rtp_arrecadacao')->default(0);
            $table->integer('rtp_distribuicao')->default(0);
            $table->decimal('total_arrecadado', 12)->nullable()->default(0);
            $table->decimal('total_distribuido', 12)->nullable()->default(0);
            $table->enum('modo', ['arrecadacao', 'distribuicao'])->nullable()->default('arrecadacao');
            $table->boolean('ativo')->nullable()->default(false);
            $table->timestamp('start_cycle_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_systems');
    }
};
