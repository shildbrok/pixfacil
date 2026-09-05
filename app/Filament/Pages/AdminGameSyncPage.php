<?php



namespace App\Filament\Pages;

use App\Models\Game;
use App\Models\Provider;
use App\Services\PlayFiverCatalogSyncService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class AdminGameSyncPage extends Page
{
    protected static string $view = 'filament.pages.admin-game-sync-page';

    protected static ?string $title = 'Sincronização PlayFiver';
    protected static ?string $navigationLabel = 'Sincronizar Jogos';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'sincronizacao-playfiver';

    public array $providersPreview = [];
    public array $selectedProviders = [];

    public bool $updateExistingProviders = true;
    public bool $updateExistingGames = false;
    public string $importGameType = 'Slots';

    public ?array $lastSyncResult = null;
    public ?array $lastCodeUpdateResult = null;
    public ?string $lastActionAt = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function loadProviders(PlayFiverCatalogSyncService $service): void
    {
        try {
            $this->providersPreview = $service->previewProviders();
            $this->selectedProviders = [];
            $this->lastActionAt = now()->format('d/m/Y H:i:s');

            Notification::make()
                ->title('Provedores carregados')
                ->body('Total encontrado: ' . count($this->providersPreview))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao consultar provedores')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function selectAllProviders(): void
    {
        $this->selectedProviders = collect($this->providersPreview)
            ->pluck('remote_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function selectOnlyNewProviders(): void
    {
        $this->selectedProviders = collect($this->providersPreview)
            ->filter(fn (array $provider): bool => ! (bool) ($provider['local_provider_exists'] ?? false))
            ->pluck('remote_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedProviders = [];
    }

    public function syncSelected(PlayFiverCatalogSyncService $service): void
    {
        try {
            if (count($this->selectedProviders) === 0) {
                Notification::make()
                    ->title('Nenhum provedor selecionado')
                    ->body('Selecione ao menos um provedor antes de sincronizar.')
                    ->warning()
                    ->send();

                return;
            }

            $result = $service->syncSelectedProviders(
                $this->selectedProviders,
                $this->importGameType,
                $this->updateExistingProviders,
                $this->updateExistingGames
            );

            $this->lastSyncResult = $result;
            $this->lastCodeUpdateResult = null;
            $this->providersPreview = $service->previewProviders();
            $this->lastActionAt = now()->format('d/m/Y H:i:s');
            $this->clearGamesCache();

            Notification::make()
                ->title('Sincronização concluída')
                ->body($this->syncSummary($result))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao sincronizar jogos')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updateGameCodes(PlayFiverCatalogSyncService $service): void
    {
        try {
            if (count($this->selectedProviders) === 0) {
                Notification::make()
                    ->title('Nenhum provedor selecionado')
                    ->body('Selecione ao menos um provedor antes de atualizar os game codes.')
                    ->warning()
                    ->send();

                return;
            }

            $result = $service->updateGameCodesForSelectedProviders($this->selectedProviders);

            $this->lastCodeUpdateResult = $result;
            $this->lastSyncResult = null;
            $this->lastActionAt = now()->format('d/m/Y H:i:s');
            $this->clearGamesCache();

            Notification::make()
                ->title('Game codes atualizados')
                ->body($this->codeUpdateSummary($result))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao atualizar game_code')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function stats(): array
    {
        return [
            'providers_total' => Provider::query()->count(),
            'providers_play_fiver' => Provider::query()->where('distribution', 'play_fiver')->count(),
            'games_total' => Game::query()->count(),
            'games_play_fiver' => Game::query()->where('distribution', 'play_fiver')->count(),
            'games_active' => Game::query()->where('status', 1)->count(),
            'games_home' => Game::query()->where('show_home', 1)->count(),
            'games_featured' => Game::query()->where('is_featured', 1)->count(),
            'last_game_update' => Game::query()->latest('updated_at')->value('updated_at'),
        ];
    }

    public function remoteStats(): array
    {
        $providers = collect($this->providersPreview);

        return [
            'loaded' => $providers->count(),
            'selected' => count($this->selectedProviders),
            'already_exists' => $providers->where('local_provider_exists', true)->count(),
            'new' => $providers->where('local_provider_exists', false)->count(),
            'remote_active' => $providers->where('status', true)->count(),
            'local_games' => (int) $providers->sum('local_games_count'),
        ];
    }

    public function syncSummary(array $result): string
    {
        return sprintf(
            'Provedores: %d criados, %d atualizados, %d ignorados. Jogos: %d criados, %d atualizados, %d ignorados, %d colisões.',
            (int) ($result['providers_created'] ?? 0),
            (int) ($result['providers_updated'] ?? 0),
            (int) ($result['providers_skipped'] ?? 0),
            (int) ($result['games_created'] ?? 0),
            (int) ($result['games_updated'] ?? 0),
            (int) ($result['games_skipped'] ?? 0),
            (int) ($result['games_collision_skipped'] ?? 0),
        );
    }

    public function codeUpdateSummary(array $result): string
    {
        return sprintf(
            'Provedores verificados: %d. Jogos atualizados: %d. Mesmo código: %d. Nome não encontrado: %d. Colisões: %d.',
            (int) ($result['providers_checked'] ?? 0),
            (int) ($result['games_updated'] ?? 0),
            (int) ($result['games_skipped_same_code'] ?? 0),
            (int) ($result['games_name_not_found'] ?? 0),
            (int) ($result['games_collision_skipped'] ?? 0),
        );
    }

    public function hasSyncErrors(): bool
    {
        return ! empty($this->lastSyncResult['errors'] ?? []) || ! empty($this->lastCodeUpdateResult['errors'] ?? []);
    }

    public function syncErrors(): array
    {
        return array_values(array_filter(array_merge(
            $this->lastSyncResult['errors'] ?? [],
            $this->lastCodeUpdateResult['errors'] ?? [],
        )));
    }

    private function clearGamesCache(): void
    {
        if (method_exists(Game::class, 'clearCatalogCaches')) {
            Game::clearCatalogCaches();
        }

        foreach ([
            'games',
            'games.all',
            'games.home',
            'games.featured',
            'games.providers',
            'games.categories',
            'casino.games',
            'casino.games.home',
            'casino.games.featured',
            'api.games',
            'api.games.home',
            'api.games.featured',
            'home_games',
            'featured_games',
            'pf:v1:categories:list',
            'pf:v6:providers_with_games_priority_min',
            'pf:v7:providers_with_games_priority_min',
        ] as $key) {
            Cache::forget($key);
        }

        Cache::put('asset_version', now()->timestamp, now()->addDays(30));
    }
}
