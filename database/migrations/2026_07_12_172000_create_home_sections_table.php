<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seções DINÂMICAS da home. O cliente cria/edita/ordena/liga-desliga no admin.
 * Cada seção tem um TIPO que decide como os jogos são escolhidos:
 *   featured  -> jogos marcados como destaque (is_featured=1)
 *   popular   -> mais vistos (views desc)
 *   new       -> mais recentes (created_at desc)
 *   recent    -> os que O JOGADOR logado jogou (via orders) — por usuário
 *   category  -> jogos de uma categoria (category_id)
 *   manual    -> jogos escolhidos a dedo (pivot home_section_game)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_sections')) {
            Schema::create('home_sections', function (Blueprint $table) {
                $table->id();
                $table->string('title');                                  // ex: "EM DESTAQUE"
                $table->enum('type', ['featured', 'popular', 'new', 'recent', 'category', 'manual'])
                      ->default('category');
                $table->unsignedBigInteger('category_id')->nullable();    // usado quando type=category
                $table->string('icon')->nullable();                       // opcional (emoji/classe)
                $table->unsignedSmallInteger('games_limit')->default(12);  // quantos jogos mostrar
                $table->unsignedInteger('sort_order')->default(0);         // ordem na home
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('home_section_game')) {
            Schema::create('home_section_game', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('home_section_id')->index();
                $table->unsignedBigInteger('game_id')->index();
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        // Seed inicial (só se vazio) — o cliente edita/adiciona depois. Espelha o design.
        if (Schema::hasTable('home_sections') && DB::table('home_sections')->count() === 0) {
            $catId = fn (string $slug) => optional(DB::table('categories')->where('slug', $slug)->first())->id;

            $rows = [
                ['title' => 'Em Destaque',     'type' => 'featured', 'category_id' => null,               'games_limit' => 12, 'sort_order' => 1],
                ['title' => 'Jogos Populares', 'type' => 'popular',  'category_id' => null,               'games_limit' => 12, 'sort_order' => 2],
                ['title' => 'Cassino Ao Vivo', 'type' => 'category', 'category_id' => $catId('ao-vivo'),  'games_limit' => 12, 'sort_order' => 3],
                ['title' => 'Crash & Instant', 'type' => 'category', 'category_id' => $catId('crash'),    'games_limit' => 12, 'sort_order' => 4],
                ['title' => 'Lançamentos',     'type' => 'new',      'category_id' => null,               'games_limit' => 12, 'sort_order' => 5],
                ['title' => 'Jogos Recentes',  'type' => 'recent',   'category_id' => null,               'games_limit' => 12, 'sort_order' => 6],
            ];

            foreach ($rows as $r) {
                DB::table('home_sections')->insert(array_merge($r, [
                    'icon' => null, 'active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_game');
        Schema::dropIfExists('home_sections');
    }
};
