<?php



namespace App\Http\Controllers\Api\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Provider;
use App\Support\PlayFiverGate;
use App\Traits\Providers\PlayFiverTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    use PlayFiverTrait;

    private const CLIENT_CACHE_CONTROL = 'no-cache, private, must-revalidate';

    private function expectedDistribution(): string
    {
        return PlayFiverGate::expectedDistribution();
    }

    private function gameOrProviderAllowed(Game $game): bool
    {
        return
            PlayFiverGate::isAllowedDistribution($game->distribution) ||
            PlayFiverGate::isAllowedDistribution(optional($game->provider)->distribution);
    }


    private function gamesFingerprint(): string
    {
        $parts = [
            'games_count:' . $this->tableCount('games'),
            'games_max:' . $this->tableMaxTimestamp('games'),
            'providers_count:' . $this->tableCount('providers'),
            'providers_max:' . $this->tableMaxTimestamp('providers'),
            'categories_count:' . $this->tableCount('categories'),
            'categories_max:' . $this->tableMaxTimestamp('categories'),
            'category_games_count:' . $this->tableCount('category_games'),
            'category_games_max:' . $this->tableMaxTimestamp('category_games'),
            'distribution:' . $this->expectedDistribution(),
            'asset_version:' . Cache::get('asset_version', 'v1'),
        ];

        return md5(implode('|', $parts));
    }

    private function tableCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function tableMaxTimestamp(string $table): int
    {
        try {
            $maxUpdated = DB::table($table)->max('updated_at');

            if (! $maxUpdated) {
                $maxCreated = DB::table($table)->max('created_at');

                if (! $maxCreated) {
                    return 0;
                }

                if (is_string($maxCreated)) {
                    $ts = strtotime($maxCreated);
                    return $ts !== false ? $ts : 0;
                }

                if (method_exists($maxCreated, 'getTimestamp')) {
                    return $maxCreated->getTimestamp();
                }

                return 0;
            }

            if (is_string($maxUpdated)) {
                $ts = strtotime($maxUpdated);
                return $ts !== false ? $ts : 0;
            }

            if (method_exists($maxUpdated, 'getTimestamp')) {
                return $maxUpdated->getTimestamp();
            }

            return 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function versionedCacheKey(string $baseKey, array $context = []): string
    {
        $fingerprint = $this->gamesFingerprint();

        if (empty($context)) {
            return "{$baseKey}:{$fingerprint}";
        }

        return "{$baseKey}:{$fingerprint}:" . md5(json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    private function etagFromData(mixed $data, ?string $extra = null): string
    {
        return '"' . md5(
            ($extra ?? '') . '|' . $this->gamesFingerprint() . '|' . json_encode($data, JSON_UNESCAPED_UNICODE)
        ) . '"';
    }

    public function index(Request $request)
    {
        $ttl = now()->addDay();
        $expected = $this->expectedDistribution();
        $cacheKey = $this->versionedCacheKey('pf:v7:providers_with_games_priority_min');

        $providers = Cache::remember($cacheKey, $ttl, function () use ($expected) {
            $orderRaw = "
                CASE
                    WHEN code = 'PGSOFT' THEN 0
                    WHEN code = 'PRAGMATIC' THEN 1
                    WHEN code = 'SPRIBE' THEN 2
                    WHEN code IN ('EVOLUTION_LIVE','EVOLUTION LIVE','OFICIAL - EVOLUTION LIVE')
                        OR name LIKE '%EVOLUTION LIVE%' OR name LIKE '%EVOLUTION%' THEN 3
                    WHEN code = 'FATPANDA' THEN 4
                    WHEN code IN ('PRAGMATIC_LIVE','PRAGMATIC LIVE','OFICIAL - PRAGMATIC LIVE')
                        OR name LIKE '%PRAGMATIC LIVE%' THEN 5
                    ELSE 99
                END
            ";

            return Provider::query()
                ->where('status', 1)
                ->where('distribution', $expected)
                ->whereHas('games', fn ($q) => $q->where('status', 1))
                ->select(['id', 'cover', 'code', 'name', 'views', 'sort_order', 'distribution', 'updated_at', 'created_at'])
                ->with([
                    'games' => function ($q) {
                        $q->where('status', 1)
                            ->orderBy('views', 'desc')
                            ->select([
                                'id',
                                'provider_id',
                                'game_id',
                                'game_name',
                                'game_code',
                                'cover',
                                'views',
                                'distribution',
                                'original',
                                'updated_at',
                                'created_at',
                            ]);
                    },
                ])


                ->orderByRaw('CASE WHEN sort_order = 0 THEN 999999 ELSE sort_order END ASC')
                ->orderByRaw($orderRaw)
                ->orderBy('name', 'asc')
                ->get();
        });

        $etag = $this->etagFromData($providers, 'providers-index');
        $ifNoneMatch = $request->header('If-None-Match');

        if ($request->boolean('bust')) {
            return response()->json(['providers' => $providers])
                ->header('ETag', $etag)
                ->header('Cache-Control', self::CLIENT_CACHE_CONTROL);
        }

        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', self::CLIENT_CACHE_CONTROL);
        }

        return response()->json(['providers' => $providers])
            ->header('ETag', $etag)
            ->header('Cache-Control', self::CLIENT_CACHE_CONTROL);
    }

    public function featured(Request $request)
    {
        $ttl = now()->addDay();
        $expected = $this->expectedDistribution();
        $cacheKey = $this->versionedCacheKey('pf:v3:featured_games:min');

        $featured = Cache::remember($cacheKey, $ttl, function () use ($expected) {
            return Game::query()
                ->where('is_featured', 1)
                ->where('status', 1)
                ->whereHas('provider', function ($q) use ($expected) {
                    $q->where('distribution', $expected);
                })
                ->with([
                    'provider' => function ($q) use ($expected) {
                        $q->where('distribution', $expected)
                            ->select(['id','cover','code','name','distribution']);
                    }
                ])
                ->select([
                    'id','provider_id','game_id','game_name','game_code',
                    'cover','distribution','views','is_featured','show_home',
                    'updated_at','created_at',
                ])
                ->orderByDesc('views')
                ->get()
                ->toArray();
        });

        $etag = $this->etagFromData($featured, 'featured-games');
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response()
                ->noContent(304)
                ->setEtag($etag)
                ->header('Cache-Control', self::CLIENT_CACHE_CONTROL);
        }

        return response()
            ->json(['featured_games' => $featured])
            ->setEtag($etag)
            ->header('Cache-Control', self::CLIENT_CACHE_CONTROL);
    }

    public function show(string $id)
    {
        $game = Game::with(['categories', 'provider'])
            ->whereStatus(1)
            ->find($id);

        if (! $game) {
            return response()->json([
                'code' => 'GAME_NOT_FOUND',
                'error' => 'Jogo não encontrado.',
                'status' => false,
            ], 404);
        }

        if (! Auth::guard('api')->check()) {
            return response()->json([
                'code' => 'AUTH_REQUIRED',
                'error' => 'Você precisa tá autenticado para jogar',
                'status' => false,
            ], 400);
        }

        if (! $this->gameOrProviderAllowed($game)) {
            return response()->json([
                'code' => 'GAME_PROVIDER_NOT_ALLOWED',
                'error' => 'Provedor de jogo não permitido.',
                'status' => false,
            ], 403);
        }

        $game->increment('views');

        $token = \Helper::MakeToken([
            'id' => auth('api')->id(),
            'game' => $game->game_code,
        ]);

        $playfiver = self::playFiverLaunch($game->game_id, $game->only_demo);

        if (! isset($playfiver['launch_url']) || ! is_string($playfiver['launch_url'])) {
            return response()->json([
                'code' => 'GAME_LAUNCH_FAILED',
                'error' => 'Não foi possível abrir o jogo.',
                'status' => false,
            ], 400);
        }

        if (! PlayFiverGate::isAllowedGameUrl($playfiver['launch_url'])) {
            return response()->json([
                'code' => 'GAME_HOST_NOT_ALLOWED',
                'error' => 'Host do jogo não permitido.',
                'status' => false,
            ], 403);
        }

        return response()->json([
            'game' => $game,
            'gameUrl' => $playfiver['launch_url'],
            'token' => $token,
        ]);
    }

    public function allGames(Request $request)
    {
        $provider = $request->filled('provider') && $request->provider !== 'all'
            ? (int) $request->provider
            : 'all';

        $category = $request->filled('category') && $request->category !== 'all'
            ? (string) $request->category
            : 'all';

        $searchTerm = trim((string) ($request->searchTerm ?? ''));
        $page = (int) $request->get('page', 1);

        // Ordem pedida pela fileira de origem da home. Lista fechada: o valor
        // vira ORDER BY, então nada de aceitar coluna arbitrária da URL.
        $sort = in_array($request->get('sort'), ['new', 'popular', 'featured'], true)
            ? (string) $request->get('sort')
            : 'popular';

        $ttlNoSearch = now()->addDay();
        $ttlWithSearch = now()->addMinutes(20);
        $ttl = strlen($searchTerm) >= 3 ? $ttlWithSearch : $ttlNoSearch;
        $expected = $this->expectedDistribution();

        $cacheKey = $this->versionedCacheKey('pf:v3:all_games', [
            'provider' => $provider,
            'category' => $category,
            'searchTerm' => mb_strtolower($searchTerm),
            'sort' => $sort,
            'page' => $page,
            'distribution' => $expected,
        ]);

        $games = Cache::remember($cacheKey, $ttl, function () use ($provider, $category, $searchTerm, $sort, $request, $expected) {
            $query = Game::query()
                ->with([
                    'provider' => function ($q) {
                        $q->select([
                            'id','cover','code','name','status','rtp','views','distribution','created_at','updated_at'
                        ]);
                    },
                    'categories:id,name,slug'
                ])
                ->select([
                    'id','provider_id','game_name','game_code','cover','distribution','views',
                ])
                ->where('status', 1)
                ->whereHas('provider', function ($q) use ($expected) {
                    $q->where('distribution', $expected);
                });

            if ($provider !== 'all') {
                $query->where('provider_id', $provider);
            }

            if ($category !== 'all') {
                $query->whereHas('categories', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            }

            if (strlen($searchTerm) >= 3) {
                $query->whereLike(['game_code', 'game_name', 'distribution', 'provider.name'], $searchTerm);
            }

            // Espelha o gamesForSection do HomeController: a fileira e o "Ver todos"
            // precisam ordenar igual, senão o destino contradiz o que a home mostrou.
            if ($sort === 'new') {
                $query->orderBy('created_at', 'desc');
            } elseif ($sort === 'featured') {
                $query->where('is_featured', 1)->orderBy('views', 'desc');
            } else {
                $query->orderBy('views', 'desc');
            }

            // Desempate estável: sem isso, linhas com o mesmo views/created_at podem
            // trocar de página entre requisições e o "Ver Mais" duplica ou pula jogos.
            $query->orderBy('id', 'desc');

            return $query->paginate(12)->appends($request->query());
        });

        $ids = implode(',', collect($games->items())->pluck('id')->all());
        $etag = $this->etagFromData([
            'ids' => $ids,
            'page' => $games->currentPage(),
            'lastPage' => $games->lastPage(),
            'total' => $games->total(),
        ], 'all-games');

        $ifNoneMatch = $request->header('If-None-Match');

        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', self::CLIENT_CACHE_CONTROL);
        }

        // Enxuga cada jogo do paginador para SÓ os campos de exibição (mantém a paginação).
        $games->through(fn ($g) => [
            'id'           => $g->id,
            'game_name'    => $g->game_name,
            'game_code'    => $g->game_code,
            'cover'        => $g->cover,
            'distribution' => $g->distribution,
            'provider'     => optional($g->provider)->name,
        ]);

        return response()->json(['games' => $games])
            ->header('Cache-Control', self::CLIENT_CACHE_CONTROL)
            ->header('ETag', $etag);
    }

    /**
     * C-01: métodos legados consumidos por CassinoSearch.vue
     * (jogos/lista, jogos/procurar, jogos/categorias, jogos/provedora).
     * Retornam { jogos: [...] } respeitando o mesmo gate de distribuição
     * (PlayFiverGate) e status=1 dos demais endpoints. Antes não existiam → 500.
     */
    private function baseGamesQuery()
    {
        return Game::query()
            ->with(['provider:id,code,name,distribution'])
            ->select([
                'id', 'provider_id', 'game_name', 'game_code', 'cover', 'distribution',
            ])
            ->where('status', 1)
            ->whereHas('provider', function ($q) {
                $q->where('distribution', $this->expectedDistribution());
            });
    }

    /** Mapeia jogos para SÓ os campos de exibição (evita accessors auto-anexados do model). */
    private function leanGames($games)
    {
        return $games->map(fn ($g) => [
            'id'           => $g->id,
            'game_name'    => $g->game_name,
            'game_code'    => $g->game_code,
            'cover'        => $g->cover,
            'distribution' => $g->distribution,
            'provider'     => optional($g->provider)->name,
        ])->values();
    }

    public function listarTodos(Request $request)
    {
        $jogos = $this->baseGamesQuery()
            ->orderBy('game_name')
            ->limit(100)
            ->get();

        return response()->json(['jogos' => $this->leanGames($jogos)]);
    }

    public function buscarPorNome(Request $request)
    {
        $termo = trim((string) $request->get('query', $request->get('q', '')));

        $q = $this->baseGamesQuery();

        if ($termo !== '') {
            $q->where(function ($sub) use ($termo) {
                $sub->where('game_name', 'like', "%{$termo}%")
                    ->orWhere('game_code', 'like', "%{$termo}%");
            });
        }

        $jogos = $q->orderBy('game_name')->limit(100)->get();

        return response()->json(['jogos' => $this->leanGames($jogos)]);
    }

    public function buscarPorCategoria(Request $request)
    {
        $categoria = trim((string) $request->get('categoria', $request->get('category', '')));

        $q = $this->baseGamesQuery();

        if ($categoria !== '' && strtolower($categoria) !== 'all') {
            $q->whereHas('categories', function ($sub) use ($categoria) {
                $sub->where('slug', $categoria)->orWhere('name', $categoria);
            });
        }

        $jogos = $q->orderBy('game_name')->limit(100)->get();

        return response()->json(['jogos' => $this->leanGames($jogos)]);
    }

    public function buscarPorProvedora(Request $request)
    {
        $provedora = trim((string) $request->get('provedora', $request->get('provider', '')));

        $q = $this->baseGamesQuery();

        if ($provedora !== '' && strtolower($provedora) !== 'all') {
            $q->whereHas('provider', function ($sub) use ($provedora) {
                $sub->where('code', $provedora)->orWhere('name', $provedora);
            });
        }

        $jogos = $q->orderBy('game_name')->limit(100)->get();

        return response()->json(['jogos' => $this->leanGames($jogos)]);
    }

    public function webhookPlayFiver(Request $request)
    {
        return self::webhookPlayFiverAPI($request);
    }
}