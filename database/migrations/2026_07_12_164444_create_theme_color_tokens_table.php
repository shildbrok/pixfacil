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
        Schema::create('theme_color_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token', 150)->unique('uq_theme_color_tokens_token');
            $table->string('label', 150);
            $table->string('family', 100)->index('idx_theme_color_tokens_family');
            $table->string('group_name', 100)->index('idx_theme_color_tokens_group');
            $table->string('color_value', 50);
            $table->string('color_format', 10);
            $table->string('css_variable', 150);
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_active')->default(true)->index('idx_theme_color_tokens_active');
            $table->integer('sort_order')->default(0);
            $table->integer('total_sources')->default(0);
            $table->integer('total_occurrences')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_color_tokens');
    }
};
