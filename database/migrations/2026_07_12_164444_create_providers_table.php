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
        Schema::create('providers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cover', 255)->nullable();
            $table->string('code', 50)->nullable();
            $table->string('name', 50)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->bigInteger('rtp')->nullable()->default(90);
            $table->bigInteger('views')->nullable()->default(1);
            $table->integer('sort_order')->default(0);
            $table->string('distribution', 50)->nullable()->default('play_fiver');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
