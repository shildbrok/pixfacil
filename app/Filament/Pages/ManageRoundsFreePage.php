<?php



namespace App\Filament\Pages;

use App\Services\PlayFiverService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageRoundsFreePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationLabel = 'Gestão de Rodadas Grátis';
    protected static ?string $title = 'Gestão de Rodadas Grátis';
    protected static ?int $navigationSort = 32;

    protected static string $view = 'filament.pages.manage-rounds-free-page';

    public array $items = [];
    public string $message = '';
    public ?string $lastLoadedAt = null;

    public string $searchUser = '';
    public string $searchGame = '';
    public string $statusFilter = '';
    public ?string $createdFrom = null;
    public ?string $createdUntil = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(): void
    {
        $this->loadFreeBonus();
    }

    public function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Atualizar lista')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadFreeBonus()),
        ];
    }

    public function loadFreeBonus(): void
    {
        $result = PlayFiverService::listFreeBonus();

        $this->items = array_values((array) ($result['data'] ?? []));
        $this->message = (string) ($result['message'] ?? '');
        $this->lastLoadedAt = now()->format('d/m/Y H:i:s');

        if (($result['status'] ?? false) !== true) {
            Notification::make()
                ->title('Falha ao listar rodadas grátis')
                ->body($this->message ?: 'Não foi possível listar as rodadas grátis na PlayFiver.')
                ->danger()
                ->send();
        }
    }

    public function clearFilters(): void
    {
        $this->searchUser = '';
        $this->searchGame = '';
        $this->statusFilter = '';
        $this->createdFrom = null;
        $this->createdUntil = null;
    }

    public function removeFreeBonus(int $id): void
    {
        if ($id < 1) {
            Notification::make()
                ->title('ID inválido')
                ->body('A PlayFiver não retornou um ID válido para essa rodada.')
                ->danger()
                ->send();

            return;
        }

        $row = collect($this->normalizedItems())
            ->first(fn (array $item) => (int) ($item['id'] ?? 0) === $id);

        if (($row['status_normalized'] ?? '') === 'completed') {
            Notification::make()
                ->title('Rodada já concluída')
                ->body('Rodadas com status completed são apenas histórico e não podem ser removidas.')
                ->warning()
                ->send();

            return;
        }

        $result = PlayFiverService::deleteFreeBonus($id);

        if (($result['status'] ?? false) === true) {
            Notification::make()
                ->title('Rodadas removidas do cliente')
                ->body($result['message'] ?? 'Rodadas grátis removidas com sucesso na PlayFiver.')
                ->success()
                ->send();

            $this->loadFreeBonus();

            return;
        }

        Notification::make()
            ->title('Falha ao remover rodadas')
            ->body($result['message'] ?? 'Não foi possível remover as rodadas grátis.')
            ->danger()
            ->send();
    }

    public function normalizedItems(): array
    {
        return collect($this->items)
            ->map(function ($item): array {
                $item = (array) $item;
                $raw = (array) ($item['raw'] ?? $item);

                $id = $item['id'] ?? $raw['id'] ?? $raw['_id'] ?? null;
                $player = $item['user_code']
                    ?? $item['player_id']
                    ?? $item['username']
                    ?? $raw['player_id']
                    ?? $raw['user_code']
                    ?? $raw['username']
                    ?? $raw['user']
                    ?? '-';

                $gameCode = $item['game_code']
                    ?? $item['game_id']
                    ?? $raw['game_code']
                    ?? $raw['game_id']
                    ?? $raw['game']
                    ?? '-';

                $gameName = $item['game_name']
                    ?? $raw['game_name']
                    ?? $raw['name']
                    ?? null;

                $rounds = $item['rounds'] ?? $raw['rounds'] ?? 0;
                $totalRounds = $item['total_rounds'] ?? $raw['total_rounds'] ?? $rounds;
                $status = (string) ($item['status'] ?? $raw['status'] ?? '-');
                $statusNormalized = mb_strtolower(trim($status));
                $createdAt = $item['created_at'] ?? $raw['created_at'] ?? $raw['createdAt'] ?? null;

                return [
                    'id' => $id,
                    'player' => $player ?: '-',
                    'game_code' => $gameCode ?: '-',
                    'game_name' => $gameName ?: '-',
                    'rounds' => (int) $rounds,
                    'total_rounds' => (int) $totalRounds,
                    'used_rounds' => max(0, (int) $totalRounds - (int) $rounds),
                    'status' => $status ?: '-',
                    'status_normalized' => $statusNormalized,
                    'created_at' => $createdAt,
                    'created_at_br' => $this->formatDate($createdAt),
                    'removable' => is_numeric($id) && $statusNormalized !== 'completed',
                    'raw' => $raw,
                ];
            })
            ->values()
            ->all();
    }

    public function filteredItems(): array
    {
        return collect($this->normalizedItems())
            ->filter(function (array $item): bool {
                if ($this->searchUser !== '') {
                    $needle = mb_strtolower(trim($this->searchUser));
                    $haystack = mb_strtolower((string) $item['player']);

                    if (! str_contains($haystack, $needle)) {
                        return false;
                    }
                }

                if ($this->searchGame !== '') {
                    $needle = mb_strtolower(trim($this->searchGame));
                    $haystack = mb_strtolower((string) $item['game_code'] . ' ' . (string) $item['game_name']);

                    if (! str_contains($haystack, $needle)) {
                        return false;
                    }
                }

                if ($this->statusFilter !== '' && $item['status_normalized'] !== mb_strtolower($this->statusFilter)) {
                    return false;
                }

                if ($this->createdFrom || $this->createdUntil) {
                    $date = $this->parseDate($item['created_at']);

                    if (! $date) {
                        return false;
                    }

                    if ($this->createdFrom && $date->lt(\Carbon\Carbon::parse($this->createdFrom)->startOfDay())) {
                        return false;
                    }

                    if ($this->createdUntil && $date->gt(\Carbon\Carbon::parse($this->createdUntil)->endOfDay())) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }

    public function counters(): array
    {
        $all = collect($this->normalizedItems());
        $filtered = collect($this->filteredItems());

        return [
            'total' => $all->count(),
            'filtered' => $filtered->count(),
            'completed' => $filtered->where('status_normalized', 'completed')->count(),
            'removable' => $filtered->where('removable', true)->count(),
        ];
    }

    private function formatDate($value): string
    {
        $date = $this->parseDate($value);

        return $date ? $date->format('d/m/Y H:i') : '-';
    }

    private function parseDate($value): ?\Carbon\Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}