<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('house_games')) {
            Schema::create('house_games', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('slug', 80)->unique();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->string('cover', 500)->nullable();
                $table->string('icon', 500)->nullable();
                $table->string('engine_path', 191);
                $table->boolean('active')->default(true)->index();
                $table->boolean('show_home')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->unsignedBigInteger('views')->default(0);
                $table->decimal('min_bet', 20, 2)->default(1);
                $table->decimal('max_bet', 20, 2)->default(100);
                $table->decimal('coin_rate', 12, 6)->default(0.01);
                $table->decimal('meta_multiplier', 12, 4)->default(2);
                $table->decimal('max_win_multiplier', 12, 4)->default(10);
                $table->decimal('player_speed', 12, 4)->default(1);
                $table->json('engine_params')->nullable();
                $table->unsignedInteger('min_win_seconds')->default(2);
                $table->unsignedInteger('round_timeout_seconds')->default(1800);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('house_game_rounds')) {
            Schema::create('house_game_rounds', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->uuid('round_uuid')->unique();
                $table->uuid('client_event_id')->nullable();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('house_game_id')->index();
                $table->string('game_slug', 80)->index();
                $table->decimal('bet', 20, 2);
                $table->decimal('meta_amount', 20, 2);
                $table->decimal('max_payout', 20, 2);
                $table->decimal('client_claim', 20, 2)->default(0);
                $table->decimal('payout', 20, 2)->default(0);
                $table->string('status', 32)->default('opening')->index();
                $table->string('type_money', 64)->nullable();
                $table->json('debits')->nullable();
                $table->json('rollover_before_bet')->nullable();
                $table->char('engine_token_hash', 64)->nullable()->index();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('launched_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'client_event_id'], 'house_round_client_event_unique');
                $table->index(['user_id', 'game_slug', 'status'], 'house_round_user_game_status');
            });
        }

        $now = now();
        $games = [
            [
                'slug' => 'sub', 'name' => 'Subway Money',
                'description' => 'Corra pelas trilhas, desvie dos obstáculos e acumule dinheiro até atingir a meta.',
                'cover' => 'retro-games/_media/sub/banner.png', 'icon' => 'retro-games/_media/sub/icon.png',
                'engine_path' => 'sub', 'sort_order' => 10, 'coin_rate' => 0.500000,
                'meta_multiplier' => 1.0000, 'max_win_multiplier' => 3.0000, 'player_speed' => 8,
                'engine_params' => json_encode(new stdClass()),
            ],
            [
                'slug' => 'fruit', 'name' => 'Fruit Ninja',
                'description' => 'Corte as frutas no ar, evite as bombas e alcance a meta da rodada.',
                'cover' => 'retro-games/_media/fruit/banner.webp', 'icon' => 'retro-games/_media/fruit/icon.webp',
                'engine_path' => 'fruit', 'sort_order' => 20, 'coin_rate' => 0.010000,
                'meta_multiplier' => 50.0000, 'max_win_multiplier' => 150.0000, 'player_speed' => 1,
                'engine_params' => json_encode(['fruit_rate' => 6, 'drop_duration' => 1000]),
            ],
            [
                'slug' => 'dino', 'name' => 'DinoWin',
                'description' => 'Corra com o dinossauro, desvie dos cactos e sobreviva até bater a meta.',
                'cover' => 'retro-games/_media/dino/banner.png', 'icon' => 'retro-games/_media/dino/icon.png',
                'engine_path' => 'dino', 'sort_order' => 30, 'coin_rate' => 0.010000,
                'meta_multiplier' => 100.0000, 'max_win_multiplier' => 300.0000, 'player_speed' => 15,
                'engine_params' => json_encode(new stdClass()),
            ],
            [
                'slug' => 'angry', 'name' => 'Angry Cash',
                'description' => 'Mire e derrube as estruturas para acumular valor e fechar a rodada.',
                'cover' => 'retro-games/_media/angry/banner.png', 'icon' => 'retro-games/_media/angry/icon.png',
                'engine_path' => 'angry', 'sort_order' => 40, 'coin_rate' => 0.050000,
                'meta_multiplier' => 4.0000, 'max_win_multiplier' => 12.0000, 'player_speed' => 1,
                'engine_params' => json_encode(['game_difficulty' => 1]),
            ],
            [
                'slug' => 'candy', 'name' => 'Candy Cash',
                'description' => 'Combine os doces contra o relógio e alcance a meta antes do tempo acabar.',
                'cover' => 'retro-games/_media/candy/banner.webp', 'icon' => 'retro-games/_media/candy/icon.webp',
                'engine_path' => 'candy', 'sort_order' => 50, 'coin_rate' => 0.010000,
                'meta_multiplier' => 6.0000, 'max_win_multiplier' => 18.0000, 'player_speed' => 1,
                'engine_params' => json_encode(['timer' => 110]),
            ],
            [
                'slug' => 'jetpack', 'name' => 'Jetpack Cash',
                'description' => 'Voe, desvie dos mísseis e junte moedas enquanto o desafio fica mais rápido.',
                'cover' => 'retro-games/_media/jetpack/banner.png', 'icon' => 'retro-games/_media/jetpack/icon.png',
                'engine_path' => 'jetpack', 'sort_order' => 60, 'coin_rate' => 0.010000,
                'meta_multiplier' => 4.0000, 'max_win_multiplier' => 12.0000, 'player_speed' => 800,
                'engine_params' => json_encode(['missile_speed' => 1800]),
            ],
            [
                'slug' => 'pacman', 'name' => 'Pacman Cash',
                'description' => 'Coma os pontos, fuja dos fantasmas e acumule valor antes de perder a rodada.',
                'cover' => 'retro-games/_media/pacman/banner.jpg', 'icon' => 'retro-games/_media/pacman/icon.webp',
                'engine_path' => 'pacman', 'sort_order' => 70, 'coin_rate' => 0.010000,
                'meta_multiplier' => 10.0000, 'max_win_multiplier' => 30.0000, 'player_speed' => 1,
                'engine_params' => json_encode(['lives' => 0, 'ghost_points' => 0.1]),
            ],
            [
                'slug' => 'helix', 'name' => 'Helix Cash',
                'description' => 'Gire a torre, atravesse as aberturas e evite as áreas perigosas.',
                'cover' => 'retro-games/_media/helix/banner.png', 'icon' => 'retro-games/_media/helix/icon.png',
                'engine_path' => 'helix', 'sort_order' => 80, 'coin_rate' => 0.010000,
                'meta_multiplier' => 2.0000, 'max_win_multiplier' => 6.0000, 'player_speed' => 1,
                'engine_params' => json_encode([
                    'gravity' => 0.0085, 'open_percent' => 0.18, 'danger_percent' => 0.20,
                    'base_earn' => 0.10, 'speed_increase' => 0.0004,
                ]),
            ],
            [
                'slug' => 'blockwin', 'name' => 'Block Win',
                'description' => 'Monte linhas no tabuleiro 8x8, faça combos e alcance a meta da rodada.',
                'cover' => 'retro-games/_media/blockwin/banner.png', 'icon' => 'retro-games/_media/blockwin/icon.png',
                'engine_path' => 'blockwin', 'sort_order' => 90, 'coin_rate' => 0.010000,
                'meta_multiplier' => 2.0000, 'max_win_multiplier' => 6.0000, 'player_speed' => 1,
                'engine_params' => json_encode(['difficulty' => 'normal', 'score_multiplier' => 1, 'easy_start_moves' => 4]),
            ],
        ];

        foreach ($games as $game) {
            DB::table('house_games')->updateOrInsert(
                ['slug' => $game['slug']],
                array_merge([
                    'active' => true,
                    'show_home' => true,
                    'views' => 0,
                    'min_bet' => 1,
                    'max_bet' => 100,
                    'min_win_seconds' => 2,
                    'round_timeout_seconds' => 1800,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $game)
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('house_game_rounds');
        Schema::dropIfExists('house_games');
    }
};
