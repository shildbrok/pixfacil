<?php



namespace App\Services;

use App\Models\Game;
use App\Models\GamesKey;
use App\Models\Provider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PlayFiverCatalogSyncService
{
    public function previewProviders(): array
    {
        return collect($this->fetchRemoteProviders())
            ->map(function (array $remote): array {
                $code = $this->providerCode($remote);

                $localProvider = Provider::query()
                    ->where('code', $code)
                    ->where('distribution', 'play_fiver')
                    ->first();

                return [
                    'remote_id' => (int) data_get($remote, 'id'),
                    'code' => $code,
                    'remote_name' => (string) data_get($remote, 'name'),
                    'image_url' => (string) data_get($remote, 'image_url', ''),
                    'wallet_name' => (string) data_get($remote, 'wallet.name', ''),
                    'status' => (bool) data_get($remote, 'status', true),
                    'local_provider_exists' => (bool) $localProvider,
                    'local_provider_id' => $localProvider?->id,
                    'local_games_count' => $localProvider
                        ? Game::query()
                            ->where('provider_id', $localProvider->id)
                            ->where('distribution', 'play_fiver')
                            ->count()
                        : 0,
                ];
            })
            ->values()
            ->all();
    }

    public function syncSelectedProviders(
        array $selectedRemoteIds,
        string $gameType = 'Slots',
        bool $updateExistingProviders = true,
        bool $updateExistingGames = false
    ): array {
        $selectedRemoteIds = collect($selectedRemoteIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($selectedRemoteIds === []) {
            throw new RuntimeException('Nenhum provedor foi selecionado para sincronização.');
        }

        $remoteProviders = collect($this->fetchRemoteProviders())
            ->keyBy(fn (array $provider) => (int) data_get($provider, 'id'));

        $result = [
            'providers_created' => 0,
            'providers_updated' => 0,
            'providers_skipped' => 0,
            'games_created' => 0,
            'games_updated' => 0,
            'games_skipped' => 0,
            'games_collision_skipped' => 0,
            'errors' => [],
        ];

        foreach ($selectedRemoteIds as $remoteId) {
            $remoteProvider = $remoteProviders->get($remoteId);

            if (! $remoteProvider) {
                $result['errors'][] = "Provedor remoto {$remoteId} não encontrado.";
                continue;
            }

            try {
                DB::transaction(function () use (
                    $remoteProvider,
                    $remoteId,
                    $gameType,
                    $updateExistingProviders,
                    $updateExistingGames,
                    &$result
                ) {
                    [$provider, $providerAction] = $this->upsertProvider(
                        $remoteProvider,
                        $updateExistingProviders
                    );

                    if ($providerAction === 'created') {
                        $result['providers_created']++;
                    } elseif ($providerAction === 'updated') {
                        $result['providers_updated']++;
                    } else {
                        $result['providers_skipped']++;
                    }

                    $gamesSync = $this->syncGamesForProvider(
                        $provider,
                        $remoteId,
                        $gameType,
                        $updateExistingGames
                    );

                    $result['games_created'] += $gamesSync['created'];
                    $result['games_updated'] += $gamesSync['updated'];
                    $result['games_skipped'] += $gamesSync['skipped'];
                    $result['games_collision_skipped'] += $gamesSync['collision_skipped'];
                });
            } catch (\Throwable $e) {
                $result['errors'][] = sprintf(
                    'Erro ao sincronizar o provedor %s: %s',
                    data_get($remoteProvider, 'name', '#'.$remoteId),
                    $e->getMessage()
                );
            }
        }

        return $result;
    }

    public function updateGameCodesForSelectedProviders(array $selectedRemoteIds): array
    {
        $selectedRemoteIds = collect($selectedRemoteIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($selectedRemoteIds === []) {
            throw new RuntimeException('Nenhum provedor foi selecionado para atualização de game_code.');
        }

        $remoteProviders = collect($this->fetchRemoteProviders())
            ->keyBy(fn (array $provider) => (int) data_get($provider, 'id'));

        $result = [
            'providers_checked' => 0,
            'games_updated' => 0,
            'games_skipped_same_code' => 0,
            'games_name_not_found' => 0,
            'games_collision_skipped' => 0,
            'errors' => [],
        ];

        foreach ($selectedRemoteIds as $remoteId) {
            $remoteProvider = $remoteProviders->get($remoteId);

            if (! $remoteProvider) {
                $result['errors'][] = "Provedor remoto {$remoteId} não encontrado.";
                continue;
            }

            try {
                $providerCode = $this->providerCode($remoteProvider);

                $localProvider = Provider::query()
                    ->where('code', $providerCode)
                    ->where('distribution', 'play_fiver')
                    ->first();

                if (! $localProvider) {
                    $result['errors'][] = "Provedor local {$providerCode} não encontrado.";
                    continue;
                }

                $remoteGames = $this->fetchRemoteGamesByProvider($remoteId);
                $remoteByName = collect($remoteGames)->keyBy(function (array $game) {
                    return $this->normalizeGameName((string) data_get($game, 'name'));
                });

                $localGames = Game::query()
                    ->where('provider_id', $localProvider->id)
                    ->where('distribution', 'play_fiver')
                    ->get();

                $result['providers_checked']++;

                foreach ($localGames as $localGame) {
                    $normalizedName = $this->normalizeGameName((string) $localGame->game_name);
                    $remoteGame = $remoteByName->get($normalizedName);

                    if (! $remoteGame) {
                        $result['games_name_not_found']++;
                        continue;
                    }

                    $remoteCode = (string) data_get($remoteGame, 'game_code');

                    if ($remoteCode === '' || $remoteCode === (string) $localGame->game_code) {
                        $result['games_skipped_same_code']++;
                        continue;
                    }

                    $collision = Game::query()
                        ->where('provider_id', $localProvider->id)
                        ->where('distribution', 'play_fiver')
                        ->where('game_code', $remoteCode)
                        ->where('id', '!=', $localGame->id)
                        ->exists();

                    if ($collision) {
                        $result['games_collision_skipped']++;
                        continue;
                    }

                    $localGame->game_code = $remoteCode;
                    $localGame->game_id = $remoteCode;
                    $localGame->save();

                    $result['games_updated']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = sprintf(
                    'Erro ao atualizar códigos do provedor %s: %s',
                    data_get($remoteProvider, 'name', '#'.$remoteId),
                    $e->getMessage()
                );
            }
        }

        return $result;
    }

    public function fetchRemoteProviders(): array
    {
        $response = Http::withOptions(['force_ip_resolve' => 'v4'])->acceptJson()
            ->timeout(30)
            ->get($this->baseUrl().'/api/v2/providers');

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao consultar provedores na PlayFiver: HTTP '.$response->status().'.');
        }

        $json = $response->json();

        if (! is_array($json) || (int) Arr::get($json, 'status', 0) !== 1) {
            throw new RuntimeException('A PlayFiver respondeu sem sucesso ao listar provedores.');
        }

        $providers = Arr::get($json, 'data', []);

        if (! is_array($providers)) {
            throw new RuntimeException('Resposta inválida ao listar provedores.');
        }

        return collect($providers)
            ->filter(fn ($provider) => is_array($provider) && filled(data_get($provider, 'id')) && filled(data_get($provider, 'name')))
            ->values()
            ->all();
    }

    public function fetchRemoteGamesByProvider(int $remoteProviderId): array
    {
        $response = Http::withOptions(['force_ip_resolve' => 'v4'])->acceptJson()
            ->timeout(60)
            ->get($this->baseUrl().'/api/v2/games', [
                'provider' => $remoteProviderId,
            ]);

        if ($response->status() === 404) {
            throw new RuntimeException("O provedor remoto {$remoteProviderId} não foi encontrado na PlayFiver.");
        }

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao consultar jogos na PlayFiver: HTTP '.$response->status().'.');
        }

        $json = $response->json();

        if (! is_array($json) || (int) Arr::get($json, 'status', 0) !== 1) {
            throw new RuntimeException('A PlayFiver respondeu sem sucesso ao listar jogos.');
        }

        $games = Arr::get($json, 'data', []);

        if (! is_array($games)) {
            throw new RuntimeException('Resposta inválida ao listar jogos.');
        }

        return collect($games)
            ->filter(fn ($game) => is_array($game) && filled(data_get($game, 'game_code')))
            ->values()
            ->all();
    }

    private function upsertProvider(array $remoteProvider, bool $updateExistingProviders): array
    {
        $code = $this->providerCode($remoteProvider);

        $provider = Provider::query()
            ->where('code', $code)
            ->where('distribution', 'play_fiver')
            ->first();

        if (! $provider) {
            $provider = new Provider();
            $provider->fill($this->mapProviderPayload($remoteProvider, null));
            $provider->save();

            return [$provider, 'created'];
        }

        if (! $updateExistingProviders) {
            return [$provider, 'skipped'];
        }

        $provider->fill($this->mapProviderPayload($remoteProvider, $provider));
        $provider->save();

        return [$provider, 'updated'];
    }

    private function syncGamesForProvider(
        Provider $provider,
        int $remoteProviderId,
        string $gameType,
        bool $updateExistingGames
    ): array {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'collision_skipped' => 0,
        ];

        $remoteGames = $this->fetchRemoteGamesByProvider($remoteProviderId);

        foreach ($remoteGames as $remoteGame) {
            $gameCode = (string) data_get($remoteGame, 'game_code');
            $remoteName = (string) data_get($remoteGame, 'name');

            $game = Game::query()
                ->where('provider_id', $provider->id)
                ->where('distribution', 'play_fiver')
                ->where('game_code', $gameCode)
                ->first();

            if (! $game) {
                $game = Game::query()
                    ->where('provider_id', $provider->id)
                    ->where('distribution', 'play_fiver')
                    ->get()
                    ->first(function ($localGame) use ($remoteName) {
                        return $this->normalizeGameName((string) $localGame->game_name) === $this->normalizeGameName($remoteName);
                    });
            }

            if (! $game) {
                $game = new Game();
                $game->fill($this->mapGamePayload($provider, $remoteGame, null, $gameType));
                $game->save();
                $stats['created']++;
                continue;
            }

            if (! $updateExistingGames) {
                $stats['skipped']++;
                continue;
            }

            $newCode = (string) data_get($remoteGame, 'game_code');

            $collision = Game::query()
                ->where('provider_id', $provider->id)
                ->where('distribution', 'play_fiver')
                ->where('game_code', $newCode)
                ->where('id', '!=', $game->id)
                ->exists();

            if ($collision) {
                $stats['collision_skipped']++;
                continue;
            }

            $game->fill($this->mapGamePayload($provider, $remoteGame, $game, $gameType));
            $game->save();
            $stats['updated']++;
        }

        return $stats;
    }

    private function mapProviderPayload(array $remoteProvider, ?Provider $existing): array
    {
        $code = $this->providerCode($remoteProvider);

        return [
            'cover' => (string) data_get($remoteProvider, 'image_url', $existing?->cover ?? ''),
            'code' => $code,
            'name' => $existing?->name ?: $code,
            'status' => data_get($remoteProvider, 'status', true) ? 1 : 0,
            'rtp' => $existing?->rtp ?? 0,
            'views' => $existing?->views ?? 0,
            'distribution' => 'play_fiver',
        ];
    }

    private function mapGamePayload(Provider $provider, array $remoteGame, ?Game $existing, string $gameType): array
    {
        return [
            'provider_id' => $provider->id,
            'game_server_url' => $existing?->game_server_url ?? 'inativo',
            'game_id' => (string) data_get($remoteGame, 'game_code'),
            'game_name' => (string) data_get($remoteGame, 'name', $existing?->game_name ?? data_get($remoteGame, 'game_code')),
            'game_code' => (string) data_get($remoteGame, 'game_code'),
            'game_type' => $gameType,
            'description' => $existing?->description,
            'cover' => (string) data_get($remoteGame, 'image_url', $existing?->cover ?? ''),
            'status' => 0,
            'technology' => $existing?->technology ?? 'html5',
            'has_lobby' => $existing?->has_lobby ?? 0,
            'is_mobile' => $existing?->is_mobile ?? 0,
            'has_freespins' => data_get($remoteGame, 'rounds_free', false) ? 1 : 0,
            'has_tables' => $existing?->has_tables ?? 0,
            'only_demo' => $existing?->only_demo ?? 0,
            'rtp' => $existing?->rtp ?? 0,
            'distribution' => 'play_fiver',
            'views' => 0,
            'is_featured' => $existing?->is_featured ?? 0,
            'show_home' => 0,
            'original' => data_get($remoteGame, 'original', false) ? 1 : 0,
        ];
    }

    private function providerCode(array $remoteProvider): string
    {
        return strtoupper(trim((string) data_get($remoteProvider, 'name')));
    }

    private function normalizeGameName(string $name): string
    {
        $name = Str::ascii(Str::lower(trim($name)));
        $name = preg_replace('/[^a-z0-9]+/', '', $name);

        return $name ?: '';
    }

    private function baseUrl(): string
    {
        $keys = GamesKey::query()->first();

        return rtrim((string) ($keys?->playfiver_url ?: 'https://api.playfivers.com'), '/');
    }
}