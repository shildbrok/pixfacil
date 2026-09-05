<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seções da home (home_sections) — os blocos de jogos da página inicial.
 *
 * OBS: a migration create_home_sections_table JÁ semeia estas 6 seções num `migrate`
 * quando a tabela está vazia. Este seeder existe para o conjunto `db:seed` ficar completo
 * e para RESTAURAR a home caso as seções sejam apagadas. Ele NÃO sobrescreve uma home
 * customizada (só age se a tabela estiver vazia), espelhando a migration.
 *
 * category_id é resolvido por SLUG em tempo de execução (as categorias vêm do sync do
 * agregador e variam de ID por instalação) — nunca hardcode de IDs.
 */
class HomeSectionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('home_sections')) {
            return;
        }

        // Não mexe numa home já configurada pelo cliente.
        if (DB::table('home_sections')->count() > 0) {
            $this->command?->info('HomeSections: já existem seções, nada a fazer.');
            return;
        }

        $catId = fn (string $slug) => optional(DB::table('categories')->where('slug', $slug)->first())->id;
        $hasSubtitle = Schema::hasColumn('home_sections', 'subtitle');
        $now = now();

        $rows = [
            ['title' => 'Em Destaque',     'subtitle' => 'Em destaque',  'type' => 'featured', 'category_id' => null,             'games_limit' => 12, 'sort_order' => 1],
            ['title' => 'Jogos Populares', 'subtitle' => 'Mais jogados', 'type' => 'popular',  'category_id' => null,             'games_limit' => 12, 'sort_order' => 2],
            ['title' => 'Cassino Ao Vivo', 'subtitle' => 'Categoria',    'type' => 'category', 'category_id' => $catId('ao-vivo'), 'games_limit' => 12, 'sort_order' => 3],
            ['title' => 'Crash & Instant', 'subtitle' => 'Categoria',    'type' => 'category', 'category_id' => $catId('crash'),   'games_limit' => 12, 'sort_order' => 4],
            ['title' => 'Lançamentos',     'subtitle' => 'Novidades',    'type' => 'new',      'category_id' => null,             'games_limit' => 12, 'sort_order' => 5],
            ['title' => 'Jogos Recentes',  'subtitle' => 'Você jogou',   'type' => 'recent',   'category_id' => null,             'games_limit' => 12, 'sort_order' => 6],
        ];

        foreach ($rows as $r) {
            if (! $hasSubtitle) {
                unset($r['subtitle']);
            }
            DB::table('home_sections')->insert(array_merge($r, [
                'icon'       => null,
                'active'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command?->info('HomeSections: 6 seções padrão criadas.');
    }
}
