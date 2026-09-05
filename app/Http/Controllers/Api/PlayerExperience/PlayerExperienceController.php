<?php

namespace App\Http\Controllers\Api\PlayerExperience;

use App\Http\Controllers\Controller;
use App\Models\HouseGame;
use App\Models\HouseGameRound;
use App\Models\PlayerAchievement;
use App\Models\PlayerArcadeVisit;
use App\Models\PlayerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlayerExperienceController extends Controller
{
    private const AVATARS = ['neon', 'pixel', 'ghost', 'crown', 'bolt', 'mask', 'tiger', 'zeus', 'rabbit', 'dragon', 'fortune', 'chip'];
    private const FRAMES = ['neon', 'cyber', 'gold', 'retro', 'black', 'diamond'];

    private const ACHIEVEMENTS = [
        'arcade_first_visit' => ['title' => 'Primeiro Passo Neon', 'description' => 'Visitou seu primeiro PixFácil Original.', 'xp' => 50, 'icon' => 'game'],
        'arcade_explorer_3' => ['title' => 'Explorador Arcade', 'description' => 'Conheceu 3 Originals diferentes.', 'xp' => 150, 'icon' => 'star'],
        'arcade_explorer_5' => ['title' => 'Curador de Clássicos', 'description' => 'Conheceu 5 Originals diferentes.', 'xp' => 250, 'icon' => 'crown'],
        'arcade_explorer_all' => ['title' => 'Mapa Completo', 'description' => 'Conheceu todos os Originals disponíveis.', 'xp' => 500, 'icon' => 'trophy'],
        'first_original_round' => ['title' => 'Primeira Partida', 'description' => 'Concluiu sua primeira partida Original.', 'xp' => 100, 'icon' => 'game'],
        'originals_3' => ['title' => 'Trio Neon', 'description' => 'Concluiu partidas em 3 Originals diferentes.', 'xp' => 250, 'icon' => 'star'],
        'originals_5' => ['title' => 'Colecionador de Clássicos', 'description' => 'Concluiu partidas em 5 Originals diferentes.', 'xp' => 400, 'icon' => 'crown'],
        'originals_all' => ['title' => 'Arcade Master', 'description' => 'Concluiu partidas em todos os Originals disponíveis.', 'xp' => 1000, 'icon' => 'trophy'],
        'identity_created' => ['title' => 'Identidade PixFácil', 'description' => 'Criou um apelido para o seu perfil Arcade.', 'xp' => 100, 'icon' => 'user'],
    ];

    public function overview(): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($this->payload((int) $user->id, true));
    }

    public function sync(): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($this->payload((int) $user->id, true));
    }

    public function visit(string $slug): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $game = HouseGame::query()->where('slug', $slug)->where('active', true)->first();
        if (! $game) {
            return response()->json(['message' => 'Jogo não encontrado.'], 404);
        }

        $now = now();
        $visit = PlayerArcadeVisit::query()->firstOrNew([
            'user_id' => $user->id,
            'game_slug' => $slug,
        ]);

        if (! $visit->exists) {
            $visit->visits = 1;
            $visit->first_seen_at = $now;
        } else {
            $visit->visits = max(1, (int) $visit->visits) + 1;
        }
        $visit->last_seen_at = $now;
        $visit->save();

        return response()->json($this->payload((int) $user->id, true));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $profile = PlayerProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['avatar_key' => 'neon', 'frame_key' => 'neon', 'leaderboard_opt_in' => false, 'arcade_xp' => 0]
        );

        $validated = $request->validate([
            'nickname' => [
                'nullable', 'string', 'min:3', 'max:18', 'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('player_profiles', 'nickname')->ignore($profile->id),
            ],
            'avatar_key' => ['required', Rule::in(self::AVATARS)],
            'frame_key' => ['required', Rule::in(self::FRAMES)],
            'leaderboard_opt_in' => ['required', 'boolean'],
        ], [
            'nickname.regex' => 'Use apenas letras, números, ponto, traço ou underline no apelido.',
            'nickname.unique' => 'Esse apelido já está sendo usado.',
        ]);

        $profile->fill([
            'nickname' => filled($validated['nickname'] ?? null) ? strtolower((string) $validated['nickname']) : null,
            'avatar_key' => $validated['avatar_key'],
            'frame_key' => $validated['frame_key'],
            'leaderboard_opt_in' => (bool) $validated['leaderboard_opt_in'],
        ])->save();

        return response()->json($this->payload((int) $user->id, true));
    }

    private function payload(int $userId, bool $sync): array
    {
        $newAchievements = $sync ? $this->syncAchievements($userId) : [];
        $profile = PlayerProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['avatar_key' => 'neon', 'frame_key' => 'neon', 'leaderboard_opt_in' => false, 'arcade_xp' => 0]
        );

        $activeGames = HouseGame::query()->where('active', true)->orderBy('sort_order')->get(['slug', 'name', 'cover']);
        $activeCount = max(1, $activeGames->count());
        $gameNames = $activeGames->pluck('name', 'slug');

        $visitCount = PlayerArcadeVisit::query()->where('user_id', $userId)->distinct()->count('game_slug');
        $completedDistinct = HouseGameRound::query()
            ->where('user_id', $userId)
            ->whereIn('status', [HouseGameRound::STATUS_WON, HouseGameRound::STATUS_LOST])
            ->distinct()->count('game_slug');

        $achievements = PlayerAchievement::query()
            ->where('user_id', $userId)
            ->orderByDesc('unlocked_at')
            ->get()
            ->map(fn (PlayerAchievement $item) => $this->achievementPayload($item))
            ->values();

        $monthStart = now()->copy()->startOfMonth();
        $monthEnd = now()->copy()->endOfMonth();
        $seasonXp = (int) PlayerAchievement::query()
            ->where('user_id', $userId)
            ->whereBetween('unlocked_at', [$monthStart, $monthEnd])
            ->sum('xp');

        $rankingRows = DB::table('player_profiles as p')
            ->join('player_achievements as a', 'a.user_id', '=', 'p.user_id')
            ->where('p.leaderboard_opt_in', 1)
            ->whereBetween('a.unlocked_at', [$monthStart, $monthEnd])
            ->groupBy('p.id', 'p.user_id', 'p.nickname', 'p.avatar_key', 'p.frame_key')
            ->selectRaw('p.user_id, p.nickname, p.avatar_key, p.frame_key, SUM(a.xp) as season_xp')
            ->orderByDesc('season_xp')
            ->orderBy('p.user_id')
            ->limit(10)
            ->get()
            ->values()
            ->map(function ($row, int $index) use ($userId) {
                return [
                    'position' => $index + 1,
                    'nickname' => $this->publicNickname((int) $row->user_id, $row->nickname),
                    'avatar_key' => $row->avatar_key ?: 'neon',
                    'frame_key' => $row->frame_key ?: 'neon',
                    'season_xp' => (int) $row->season_xp,
                    'me' => (int) $row->user_id === $userId,
                ];
            });

        $rounds = HouseGameRound::query()
            ->where('user_id', $userId)
            ->whereIn('status', [HouseGameRound::STATUS_WON, HouseGameRound::STATUS_LOST])
            ->orderByDesc('settled_at')
            ->limit(12)
            ->get();

        $recentGames = [];
        $activity = [];
        foreach ($rounds as $round) {
            if (! in_array($round->game_slug, $recentGames, true)) {
                $recentGames[] = $round->game_slug;
            }
            $activity[] = [
                'type' => 'round',
                'title' => (string) ($gameNames[$round->game_slug] ?? $round->game_slug),
                'subtitle' => $round->status === HouseGameRound::STATUS_WON ? 'Rodada concluída com prêmio' : 'Rodada concluída',
                'status' => $round->status,
                'at' => optional($round->settled_at ?: $round->updated_at)->toIso8601String(),
            ];
        }

        foreach (PlayerAchievement::query()->where('user_id', $userId)->orderByDesc('unlocked_at')->limit(8)->get() as $item) {
            $def = self::ACHIEVEMENTS[$item->code] ?? null;
            if (! $def) {
                continue;
            }
            $activity[] = [
                'type' => 'achievement',
                'title' => $def['title'],
                'subtitle' => 'Conquista desbloqueada · +' . (int) $item->xp . ' XP',
                'status' => 'achievement',
                'at' => optional($item->unlocked_at ?: $item->created_at)->toIso8601String(),
            ];
        }

        usort($activity, fn (array $a, array $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        $activity = array_slice($activity, 0, 3);

        return [
            'status' => true,
            'profile' => [
                'nickname' => $profile->nickname,
                'display_name' => $this->publicNickname($userId, $profile->nickname),
                'avatar_key' => $profile->avatar_key ?: 'neon',
                'frame_key' => $profile->frame_key ?: 'neon',
                'leaderboard_opt_in' => (bool) $profile->leaderboard_opt_in,
                'arcade_xp' => (int) $profile->arcade_xp,
                'season_xp' => $seasonXp,
            ],
            'avatars' => self::AVATARS,
            'frames' => self::FRAMES,
            'achievements' => $achievements,
            'new_achievements' => array_values($newAchievements),
            'challenges' => [
                [
                    'code' => 'visit_3', 'title' => 'Passeio Neon',
                    'description' => 'Conheça 3 PixFácil Originals. Não exige aposta.',
                    'progress' => min($visitCount, 3), 'target' => 3, 'xp' => 150,
                ],
                [
                    'code' => 'visit_5', 'title' => 'Curador de Clássicos',
                    'description' => 'Conheça 5 Originals diferentes. Não exige aposta.',
                    'progress' => min($visitCount, 5), 'target' => 5, 'xp' => 250,
                ],
                [
                    'code' => 'visit_all', 'title' => 'Mapa Completo',
                    'description' => 'Explore toda a coleção Arcade. Não exige aposta.',
                    'progress' => min($visitCount, $activeCount), 'target' => $activeCount, 'xp' => 500,
                ],
            ],
            'stats' => [
                'visited_games' => $visitCount,
                'completed_originals' => $completedDistinct,
                'total_originals' => $activeCount,
                'achievement_count' => $achievements->count(),
            ],
            'recent_games' => array_slice($recentGames, 0, 3),
            'recent_activity' => $activity,
            'season' => [
                'id' => $monthStart->format('Y-m'),
                'title' => 'Temporada Neon · ' . $this->monthName((int) $monthStart->format('n')),
                'starts_at' => $monthStart->toDateString(),
                'ends_at' => $monthEnd->toDateString(),
                'xp' => $seasonXp,
            ],
            'ranking' => $rankingRows,
        ];
    }

    private function syncAchievements(int $userId): array
    {
        $profile = PlayerProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['avatar_key' => 'neon', 'frame_key' => 'neon', 'leaderboard_opt_in' => false, 'arcade_xp' => 0]
        );

        $activeCount = max(1, HouseGame::query()->where('active', true)->count());
        $visitCount = PlayerArcadeVisit::query()->where('user_id', $userId)->distinct()->count('game_slug');
        $completedCount = HouseGameRound::query()
            ->where('user_id', $userId)
            ->whereIn('status', [HouseGameRound::STATUS_WON, HouseGameRound::STATUS_LOST])
            ->count();
        $completedDistinct = HouseGameRound::query()
            ->where('user_id', $userId)
            ->whereIn('status', [HouseGameRound::STATUS_WON, HouseGameRound::STATUS_LOST])
            ->distinct()->count('game_slug');

        $eligible = [
            'arcade_first_visit' => $visitCount >= 1,
            'arcade_explorer_3' => $visitCount >= 3,
            'arcade_explorer_5' => $visitCount >= 5,
            'arcade_explorer_all' => $visitCount >= $activeCount,
            'first_original_round' => $completedCount >= 1,
            'originals_3' => $completedDistinct >= 3,
            'originals_5' => $completedDistinct >= 5,
            'originals_all' => $completedDistinct >= $activeCount,
            'identity_created' => filled($profile->nickname),
        ];

        $new = [];
        foreach ($eligible as $code => $ok) {
            if (! $ok || ! isset(self::ACHIEVEMENTS[$code])) {
                continue;
            }
            $def = self::ACHIEVEMENTS[$code];
            $achievement = PlayerAchievement::query()->firstOrCreate(
                ['user_id' => $userId, 'code' => $code],
                ['xp' => (int) $def['xp'], 'unlocked_at' => now()]
            );
            if ($achievement->wasRecentlyCreated) {
                $new[] = $this->achievementPayload($achievement);
            }
        }

        $profile->arcade_xp = (int) PlayerAchievement::query()->where('user_id', $userId)->sum('xp');
        $profile->save();

        return $new;
    }

    private function achievementPayload(PlayerAchievement $item): array
    {
        $def = self::ACHIEVEMENTS[$item->code] ?? [
            'title' => $item->code,
            'description' => '',
            'xp' => (int) $item->xp,
            'icon' => 'trophy',
        ];

        return [
            'code' => $item->code,
            'title' => $def['title'],
            'description' => $def['description'],
            'xp' => (int) $item->xp,
            'icon' => $def['icon'],
            'unlocked_at' => optional($item->unlocked_at ?: $item->created_at)->toIso8601String(),
        ];
    }

    private function publicNickname(int $userId, ?string $nickname): string
    {
        if (filled($nickname)) {
            return '@' . ltrim((string) $nickname, '@');
        }
        return 'Arcade ' . strtoupper(substr(hash('sha256', 'pixfacil:' . $userId), 0, 5));
    }

    private function monthName(int $month): string
    {
        return [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'][$month] ?? 'Neon';
    }
}
