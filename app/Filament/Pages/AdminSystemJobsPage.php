<?php

namespace App\Filament\Pages;

use App\Support\OpsHealth;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Painel de "Tarefas automáticas": mostra os cron jobs que o cliente precisa
 * registrar (ex.: no CloudPanel) e, ao vivo, se o agendador e o worker estão
 * rodando — pela idade do heartbeat que cada um deixa.
 */
class AdminSystemJobsPage extends Page
{
    protected static string $view = 'filament.pages.admin-system-jobs-page';

    protected static ?string $title = 'Tarefas automáticas';
    protected static ?string $navigationLabel = 'Tarefas automáticas';
    protected static ?string $navigationGroup = 'Configurações da Plataforma';
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?int $navigationSort = 9;
    protected static ?string $slug = 'tarefas-automaticas';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /** Tudo que a view precisa, recalculado a cada carga/refresh. */
    public function getViewData(): array
    {
        return [
            'scheduler'        => OpsHealth::schedulerStatus(),
            'queue'            => OpsHealth::queueStats(),
            'schedulerCommand' => OpsHealth::schedulerCommand(),
            'phpBinary'        => OpsHealth::phpBinary(),
            'sitePath'         => base_path(),
        ];
    }

    /** Botão de recarregar: o wire:poll já atualiza, mas o botão dá feedback. */
    public function refreshStatus(): void
    {
        Notification::make()
            ->title('Status atualizado')
            ->success()
            ->send();
    }
}
