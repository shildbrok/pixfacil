<?php

namespace App\Http\Controllers\Api\Home;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\HomeSection;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoint enxuto da Home. As seções são administráveis pelo painel e o backend
 * também evita que a vitrine fique repetindo os mesmos jogos/provedores.
 */
class HomeController extends Controller
{
    private function mapGame(Game $g): array
    {
        return [
            'id' => $g->id,
            'game_name' => $g->game_name,
            'game_code' => $g->game_code,
            'cover' => $g->cover,
            'distribution' => $g->distribution,
            'provider' => optional($g->provider)->name,
            'rtp' => $g->rtp !== null ? (int) $g->rtp : null,
        ];
    }

    private function baseQuery()
    {
        return Game::query()
            ->where('status', 1)
            ->where('show_home', 1)
            ->whereHas('provider', fn ($q) => $q->where('distribution', 'play_fiver'))
            ->with('provider:id,name,code')
            ->select(
                'id',
                'provider_id',
                'game_name',
                'game_code',
                'cover',
                'distribution',
                'views',
                'rtp',
                'is_featured',
                'created_at'
            );
    }

    public function index(): JsonResponse
    {
        $userId = auth('api')->id();

        $sections = collect();
        if (Schema::hasTable('home_sections')) {
            $sections = HomeSection::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get();
        }

        if ($sections->isEmpty()) {
            $sections = $this->defaultSections();
        }

        $result = [];
        $usedGameIds = collect();

        foreach ($sections as $section) {
            $limit = max(1, min(24, (int) $section->games_limit));
            $candidateLimit = min(60, max(24, $limit * 3));
            $key = $section->id ?: $section->type;

            if ($section->type === 'recent') {
                $games = $userId
                    ? $this->recentGames($userId, $candidateLimit)
                    : collect();
            } else {
                $games = Cache::remember(
                    "home:section:{$key}:v3:{$candidateLimit}",
                    now()->addMinutes(5),
                    fn () => $this->gamesForSection($section, $candidateLimit)
                );
            }

            if ($games->isEmpty()) {
                continue;
            }

            // Seções manuais e recentes têm intenção explícita: preservamos exatamente
            // a seleção/ordem delas. Nas seções automáticas priorizamos variedade.
            $isExplicitSection = in_array($section->type, ['manual', 'recent'], true);

            if (! $isExplicitSection) {
                $games = $games
                    ->reject(fn (Game $game) => $usedGameIds->contains($game->id))
                    ->values();

                $games = $this->diversifyByProvider($games, $limit);
            } else {
                $games = $games->take($limit)->values();
            }

            if ($games->isEmpty()) {
                continue;
            }

            if (! $isExplicitSection) {
                $usedGameIds = $usedGameIds
                    ->merge($games->pluck('id'))
                    ->unique()
                    ->values();
            }

            $result[] = [
                'id' => $key,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'type' => $section->type,
                'icon' => $section->icon,
                'slug' => optional($section->category)->slug,
                'games' => $games->map(fn ($g) => $this->mapGame($g))->values(),
            ];
        }

        return response()->json(['sections' => $result])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function defaultSections(): Collection
    {
        return collect([
            new HomeSection([
                'title' => 'Em Destaque',
                'subtitle' => 'Seleção da casa',
                'type' => 'featured',
                'games_limit' => 12,
                'active' => true,
            ]),
            new HomeSection([
                'title' => 'Jogos Populares',
                'subtitle' => 'Mais jogados',
                'type' => 'popular',
                'games_limit' => 12,
                'active' => true,
            ]),
            new HomeSection([
                'title' => 'Lançamentos',
                'subtitle' => 'Novidades',
                'type' => 'new',
                'games_limit' => 12,
                'active' => true,
            ]),
        ]);
    }

    public function providers(): JsonResponse
    {
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

    private function gamesForSection(HomeSection $section, int $limit)
    {
        switch ($section->type) {
            case 'featured':
                return $this->baseQuery()
                    ->where('is_featured', 1)
                    ->orderByDesc('views')
                    ->limit($limit)
                    ->get();

            case 'popular':
                return $this->baseQuery()
                    ->orderByDesc('views')
                    ->limit($limit)
                    ->get();

            case 'new':
                return $this->baseQuery()
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get();

            case 'category':
                if (! $section->category_id) {
                    return collect();
                }

                return $this->baseQuery()
                    ->whereHas('categories', fn ($q) => $q->where('categories.id', $section->category_id))
                    ->orderByDesc('views')
                    ->limit($limit)
                    ->get();

            case 'manual':
                $ids = $section->games()->pluck('games.id');
                if ($ids->isEmpty()) {
                    return collect();
                }

                return $this->baseQuery()
                    ->whereIn('id', $ids)
                    ->orderByRaw('FIELD(id, ' . $ids->implode(',') . ')')
                    ->limit($limit)
                    ->get();

            default:
                return collect();
        }
    }

    /**
     * Faz round-robin entre provedores sem destruir a relevância da query original.
     * Ex.: ao invés de 8 jogos seguidos do mesmo provedor, intercala PG, Pragmatic,
     * Spribe, Evolution etc. e só depois volta ao mesmo provedor.
     */
    private function diversifyByProvider(Collection $games, int $limit): Collection
    {
        if ($games->count() <= 1) {
            return $games->take($limit)->values();
        }

        $providerOrder = [];
        $queues = [];

        foreach ($games as $game) {
            $providerKey = (string) ($game->provider_id ?: 'unknown');

            if (! array_key_exists($providerKey, $queues)) {
                $providerOrder[] = $providerKey;
                $queues[$providerKey] = [];
            }

            $queues[$providerKey][] = $game;
        }

        $result = collect();

        while ($result->count() < $limit) {
            $added = false;

            foreach ($providerOrder as $providerKey) {
                if (empty($queues[$providerKey])) {
                    continue;
                }

                $result->push(array_shift($queues[$providerKey]));
                $added = true;

                if ($result->count() >= $limit) {
                    break;
                }
            }

            if (! $added) {
                break;
            }
        }

        return $result->values();
    }

    private function recentGames(int $userId, int $limit)
    {
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
