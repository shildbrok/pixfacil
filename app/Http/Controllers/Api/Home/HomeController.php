<?php

namespace App\Http\Controllers\Api\Home;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\HomeSection;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoint ENXUTO da home: devolve as seções ativas (dinâmicas, geridas no admin),
 * cada uma com no máximo N jogos e SÓ os campos de exibição (id, nome, capa, code,
 * distribution, provedor). Nada de views, datas internas, provider_id, original, etc.
 *
 * Substitui o /api/games/all pesado (148KB) na home. Só retorna jogos play_fiver.
 */
class HomeController extends Controller
{
    /** Campos MÍNIMOS que o front precisa por jogo. */
    private function mapGame(Game $g): array
    {
        return [
            'id'        => $g->id,
            'game_name' => $g->game_name,
            'game_code' => $g->game_code,
            'cover'     => $g->cover,
            'distribution' => $g->distribution,
            'provider'  => optional($g->provider)->name,
            'rtp'       => $g->rtp !== null ? (int) $g->rtp : null,
        ];
    }

    /** Query base: só jogos ativos, de home, play_fiver, com o provedor (p/ o nome). */
    private function baseQuery()
    {
        return Game::query()
            ->where('status', 1)
            ->where('show_home', 1)
            ->whereHas('provider', fn ($q) => $q->where('distribution', 'play_fiver'))
            ->with('provider:id,name,code')
            ->select('id', 'provider_id', 'game_name', 'game_code', 'cover', 'distribution', 'views', 'rtp', 'is_featured', 'created_at');
    }

    public function index(): JsonResponse
    {
        $userId = auth('api')->id(); // pode ser null (visitante)

        // RESILIÊNCIA: se a tabela não existir (cliente não rodou o migrate) ou não houver
        // seções ativas, cai para seções PADRÃO calculadas na hora — a home NUNCA quebra.
        $sections = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('home_sections')) {
            $sections = HomeSection::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get();
        }
        if ($sections->isEmpty()) {
            $sections = $this->defaultSections();
        }

        $result = [];

        foreach ($sections as $section) {
            $limit = max(1, (int) $section->games_limit);
            $key = $section->id ?: $section->type; // seções padrão não têm id

            // "recent" é por-usuário (não cacheável globalmente); as outras são cacheadas.
            if ($section->type === 'recent') {
                $games = $userId ? $this->recentGames($userId, $limit) : collect();
            } else {
                $games = Cache::remember(
                    "home:section:{$key}:v2:{$limit}",
                    now()->addMinutes(5),
                    fn () => $this->gamesForSection($section, $limit)
                );
            }

            // Não retorna seção vazia (ex: "recentes" p/ visitante) — front nem mostra.
            if ($games->isEmpty()) {
                continue;
            }

            $result[] = [
                'id'    => $key,
                'title'    => $section->title,
                'subtitle' => $section->subtitle,
                'type'     => $section->type,
                'icon'  => $section->icon,
                'slug'  => optional($section->category)->slug, // p/ o "VER TODOS" quando é categoria
                'games' => $games->map(fn ($g) => $this->mapGame($g))->values(),
            ];
        }

        return response()->json(['sections' => $result])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** Seções padrão (usadas se não há tabela/seções) — a home sempre tem conteúdo. */
    private function defaultSections()
    {
        return collect([
            new HomeSection(['title' => 'Em Destaque',     'subtitle' => 'Em destaque', 'type' => 'featured', 'games_limit' => 12, 'active' => true]),
            new HomeSection(['title' => 'Jogos Populares', 'subtitle' => 'Mais jogados', 'type' => 'popular',  'games_limit' => 12, 'active' => true]),
            new HomeSection(['title' => 'Lançamentos',     'subtitle' => 'Novidades', 'type' => 'new',      'games_limit' => 12, 'active' => true]),
        ]);
    }

    /**
     * Lista ENXUTA de provedores (só logo/nome) para a fileira de logos da home.
     * Substitui o /api/games/all pesado que o ProvidersCarousel usava só p/ os logos.
     */
    public function providers(): JsonResponse
    {
        // Compatível antes e depois da migration da arte PixFácil.
        // Isso impede /api/home/providers de cair em 500 se a coluna ainda não existir.
        $hasPixFacilCover = Schema::hasColumn('providers', 'pixfacil_home_cover');
        $cacheKey = 'home:providers:v4:' . ($hasPixFacilCover ? 'pixfacil' : 'legacy');

        $providers = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($hasPixFacilCover) {
            $columns = ['id', 'name', 'code', 'cover', 'distribution'];
            if ($hasPixFacilCover) {
                $columns[] = 'pixfacil_home_cover';
            }

            return \App\Models\Provider::query()
                ->where('distribution', 'play_fiver')
                ->orderBy('sort_order')
                ->get($columns)
                ->map(function ($p) use ($hasPixFacilCover) {
                    $pixFacilCover = $hasPixFacilCover ? $p->pixfacil_home_cover : null;

                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'code' => $p->code,
                        'cover' => $p->cover,
                        'pixfacil_home_cover' => $pixFacilCover,
                        'home_cover' => $pixFacilCover,
                        'distribution' => $p->distribution,
                    ];
                })
                ->values();
        });

        return response()->json(['providers' => $providers]);
    }

    /** Resolve os jogos de uma seção conforme o tipo. */
    private function gamesForSection(HomeSection $section, int $limit)
    {
        switch ($section->type) {
            case 'featured':
                return $this->baseQuery()->where('is_featured', 1)
                    ->orderByDesc('views')->limit($limit)->get();

            case 'popular':
                return $this->baseQuery()->orderByDesc('views')->limit($limit)->get();

            case 'new':
                return $this->baseQuery()->orderByDesc('created_at')->limit($limit)->get();

            case 'category':
                if (! $section->category_id) {
                    return collect();
                }
                return $this->baseQuery()
                    ->whereHas('categories', fn ($q) => $q->where('categories.id', $section->category_id))
                    ->orderByDesc('views')->limit($limit)->get();

            case 'manual':
                // jogos escolhidos a dedo (respeita a ordem do pivot), filtrados pelo gate
                $ids = $section->games()->pluck('games.id');
                if ($ids->isEmpty()) {
                    return collect();
                }
                return $this->baseQuery()
                    ->whereIn('id', $ids)
                    ->orderByRaw('FIELD(id, ' . $ids->implode(',') . ')')
                    ->limit($limit)->get();

            default:
                return collect();
        }
    }

    /** Jogos que o jogador logado jogou (mais recentes primeiro). */
    private function recentGames(int $userId, int $limit)
    {
        // orders.game_uuid guarda o game_code; pega os distintos mais recentes.
        $codes = Order::query()
            ->where('user_id', $userId)
            ->whereNotNull('game_uuid')
            ->orderByDesc('id')
            ->pluck('game_uuid')
            ->unique()
            ->take($limit)
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return $this->baseQuery()
            ->whereIn('game_code', $codes)
            ->get()
            ->sortBy(fn ($g) => $codes->search($g->game_code))
            ->values();
    }
}
