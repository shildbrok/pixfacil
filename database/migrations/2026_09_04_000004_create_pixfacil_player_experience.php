<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_profiles')) {
            Schema::create('player_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('nickname', 24)->nullable()->unique();
                $table->string('avatar_key', 32)->default('neon');
                $table->string('frame_key', 32)->default('neon');
                $table->boolean('leaderboard_opt_in')->default(false);
                $table->unsignedInteger('arcade_xp')->default(0);
                $table->timestamps();
                $table->index(['leaderboard_opt_in', 'arcade_xp']);
            });
        }

        if (! Schema::hasTable('player_achievements')) {
            Schema::create('player_achievements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('code', 80);
                $table->unsignedInteger('xp')->default(0);
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'code']);
                $table->index(['user_id', 'unlocked_at']);
                $table->index('unlocked_at');
            });
        }

        if (! Schema::hasTable('player_arcade_visits')) {
            Schema::create('player_arcade_visits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('game_slug', 80);
                $table->unsignedInteger('visits')->default(1);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'game_slug']);
                $table->index(['user_id', 'last_seen_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_arcade_visits');
        Schema::dropIfExists('player_achievements');
        Schema::dropIfExists('player_profiles');
    }
};
