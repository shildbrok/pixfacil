<?php



namespace App\Filament\Pages;

use App\Models\Game;
use App\Models\GameSession;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class AdminGameSessionsPage extends Page
{
    protected static string $view = 'filament.pages.admin-game-sessions-page';

    protected static ?string $title = 'Sessões de Jogos';
    protected static ?string $navigationLabel = 'Sessões de Jogos';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'sessoes-de-jogos';

    public ?GameSession $selectedSession = null;
    public bool $showDetailsModal = false;

    public int $limit = 75;
    public ?string $search = null;
    public string $statusFilter = 'all';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function sessions(): Collection
    {
        $search = trim((string) $this->search);

        return GameSession::query()
            ->with('user')
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('game_id', $search)
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%")
                        ->orWhere('device', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('updated_at')
            ->limit($this->limit)
            ->get();
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['all', GameSession::STATUS_ACTIVE, GameSession::STATUS_EXPIRED, GameSession::STATUS_CLOSED], true)) {
            $status = 'all';
        }

        $this->statusFilter = $status;
    }

    public function showDetails(int $id): void
    {
        $session = GameSession::query()
            ->with('user')
            ->find($id);

        if (! $session) {
            Notification::make()
                ->title('Sessão não encontrada')
                ->danger()
                ->send();

            return;
        }

        $this->selectedSession = $session;
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->selectedSession = null;
        $this->showDetailsModal = false;
    }

    public function disconnect(int $id): void
    {
        $session = GameSession::query()->find($id);

        if (! $session) {
            Notification::make()
                ->title('Sessão não encontrada')
                ->danger()
                ->send();

            return;
        }

        if ($session->status !== GameSession::STATUS_ACTIVE) {
            Notification::make()
                ->title('Sessão não está ativa')
                ->warning()
                ->send();

            return;
        }

        $session->update([
            'status' => GameSession::STATUS_CLOSED,
            'closed_at' => now(),
            'last_ping_at' => now(),
        ]);

        Notification::make()
            ->title('Jogador desconectado')
            ->body('A sessão foi fechada. No próximo ping, o frontend deve sair do jogo.')
            ->success()
            ->send();

        if ($this->selectedSession?->id === $session->id) {
            $this->selectedSession = $session->fresh('user');
        }
    }

    public function deleteSession(int $id): void
    {
        $session = GameSession::query()->find($id);

        if (! $session) {
            Notification::make()
                ->title('Sessão não encontrada')
                ->danger()
                ->send();

            return;
        }

        if ($session->status === GameSession::STATUS_ACTIVE) {
            Notification::make()
                ->title('Não excluí uma sessão ativa')
                ->body('Desconecte a sessão ativa antes de excluir.')
                ->warning()
                ->send();

            return;
        }

        $wasSelected = $this->selectedSession?->id === $session->id;
        $session->delete();

        if ($wasSelected) {
            $this->closeDetails();
        }

        Notification::make()
            ->title('Sessão excluída')
            ->success()
            ->send();
    }

    public function deleteExpired(): void
    {
        $deleted = GameSession::query()
            ->where('status', GameSession::STATUS_EXPIRED)
            ->delete();

        Notification::make()
            ->title('Sessões expiradas excluídas')
            ->body($deleted . ' registro(s) removido(s).')
            ->success()
            ->send();
    }

    public function deleteClosed(): void
    {
        $deleted = GameSession::query()
            ->where('status', GameSession::STATUS_CLOSED)
            ->delete();

        Notification::make()
            ->title('Sessões fechadas excluídas')
            ->body($deleted . ' registro(s) removido(s).')
            ->success()
            ->send();
    }

    public function expireInactive(): void
    {
        $affected = GameSession::query()
            ->where('status', GameSession::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->where('last_ping_at', '<', now()->subMinutes(5))
                    ->orWhere(function ($q) {
                        $q->whereNull('last_ping_at')
                            ->where('updated_at', '<', now()->subMinutes(5));
                    });
            })
            ->update([
                'status' => GameSession::STATUS_EXPIRED,
                'closed_at' => now(),
            ]);

        Notification::make()
            ->title('Sessões inativas expiradas')
            ->body($affected . ' sessão(ões) atualizada(s).')
            ->success()
            ->send();
    }

    public function stats(): array
    {
        return [
            'total' => GameSession::query()->count(),
            'active' => GameSession::query()->where('status', GameSession::STATUS_ACTIVE)->count(),
            'expired' => GameSession::query()->where('status', GameSession::STATUS_EXPIRED)->count(),
            'closed' => GameSession::query()->where('status', GameSession::STATUS_CLOSED)->count(),
            'active_last_5_min' => GameSession::query()
                ->where('status', GameSession::STATUS_ACTIVE)
                ->where('last_ping_at', '>=', now()->subMinutes(5))
                ->count(),
            'inactive_active' => GameSession::query()
                ->where('status', GameSession::STATUS_ACTIVE)
                ->where(function ($query) {
                    $query->where('last_ping_at', '<', now()->subMinutes(5))
                        ->orWhereNull('last_ping_at');
                })
                ->count(),
            'today' => GameSession::query()->whereDate('created_at', today())->count(),
            'unique_players_active' => GameSession::query()
                ->where('status', GameSession::STATUS_ACTIVE)
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    public function gameName(?int $gameId): string
    {
        if (! $gameId) {
            return '—';
        }

        return (string) (Game::query()->whereKey($gameId)->value('game_name') ?: 'Jogo #' . $gameId);
    }

    public function duration(?GameSession $session): string
    {
        if (! $session || ! $session->started_at) {
            return '—';
        }

        $end = $session->closed_at ?: now();
        // (int) porque o Carbon 3 devolve float: sem o cast, uma sessão de 45
        // segundos aparecia como "45.000012s" na tela.
        $seconds = (int) max(0, $session->started_at->diffInSeconds($end));

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . 'min';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $hours . 'h ' . $minutes . 'min';
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            GameSession::STATUS_ACTIVE => 'Ativa',
            GameSession::STATUS_EXPIRED => 'Expirada',
            GameSession::STATUS_CLOSED => 'Fechada',
            default => $status ?: '—',
        };
    }

    public function statusClass(?string $status): string
    {
        return match ($status) {
            GameSession::STATUS_ACTIVE => 'gs-badge-ok',
            GameSession::STATUS_EXPIRED => 'gs-badge-warn',
            GameSession::STATUS_CLOSED => 'gs-badge-info',
            default => 'gs-badge-muted',
        };
    }

    public function filterButtonClass(string $status): string
    {
        return $this->statusFilter === $status ? 'gs-filter-active' : '';
    }
}