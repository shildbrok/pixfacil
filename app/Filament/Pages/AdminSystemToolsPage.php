<?php



namespace App\Filament\Pages;

use App\Services\CacheNuker;
use App\Support\AdminActionGuard;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class AdminSystemToolsPage extends Page
{
    protected static string $view = 'filament.pages.admin-system-tools-page';

    protected static ?string $title = 'Ferramentas do Sistema';
    protected static ?string $navigationLabel = 'Ferramentas do Sistema';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'ferramentas-do-sistema';

    public ?string $adminPin = null;
    public ?string $pendingAction = null;
    public bool $showPinModal = false;

    public ?array $lastReport = null;
    public ?string $lastAction = null;
    public ?string $lastActionAt = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function requestAction(string $action): void
    {
        if (! array_key_exists($action, $this->availableActions())) {
            Notification::make()
                ->title('Ação inválida')
                ->danger()
                ->send();

            return;
        }

        $this->pendingAction = $action;
        $this->adminPin = null;
        $this->showPinModal = true;
    }

    public function closePinModal(): void
    {
        $this->showPinModal = false;
        $this->pendingAction = null;
        $this->adminPin = null;
    }

    public function confirmAndRun(): void
    {
        $action = $this->pendingAction;

        if (! $action || ! array_key_exists($action, $this->availableActions())) {
            $this->closePinModal();

            Notification::make()
                ->title('Nenhuma ação selecionada')
                ->warning()
                ->send();

            return;
        }

        if (! app(AdminActionGuard::class)->confirm((string) $this->adminPin)) {
            Notification::make()
                ->title('Acesso negado')
                ->body('PIN administrativo inválido.')
                ->danger()
                ->send();

            return;
        }

        $this->showPinModal = false;
        $this->adminPin = null;

        match ($action) {
            'clear_application_cache' => $this->clearApplicationCache(),
            'clear_sessions' => $this->clearSessions(),
            'clear_advanced' => $this->clearAdvanced(),
            'run_optimize_clear' => $this->runOptimizeClear(),
            'rebuild_caches' => $this->rebuildCaches(),
            'create_storage_link' => $this->createStorageLink(),
            'restart_queue' => $this->restartQueue(),
            default => null,
        };

        $this->pendingAction = null;
    }

    public function clearApplicationCache(): void
    {
        try {
            $this->setReport(
                'Cache da aplicação limpo',
                app(CacheNuker::class)->run([
                    'deep' => true,
                    'sessions' => false,
                    'queues' => false,
                ])
            );

            $this->notifySuccess('Cache da aplicação limpo com sucesso.');
        } catch (\Throwable $e) {
            $this->notifyError('Erro ao limpar cache', $e);
        }
    }

    public function clearSessions(): void
    {
        try {
            $this->setReport(
                'Sessões limpas',
                app(CacheNuker::class)->run([
                    'deep' => false,
                    'sessions' => true,
                    'queues' => false,
                ])
            );

            $this->notifySuccess('Sessões limpas com sucesso.');
        } catch (\Throwable $e) {
            $this->notifyError('Erro ao limpar sessões', $e);
        }
    }

    public function clearAdvanced(): void
    {
        try {
            $this->setReport(
                'Limpeza avançada concluída',
                app(CacheNuker::class)->run([
                    'deep' => true,
                    'sessions' => false,
                    'queues' => true,
                ])
            );

            $this->notifySuccess('Limpeza avançada concluída.');
        } catch (\Throwable $e) {
            $this->notifyError('Erro na limpeza avançada', $e);
        }
    }

    public function rebuildCaches(): void
    {
        $report = [
            'artisan' => [],
            'errors' => [],
        ];

        foreach ([
            'config:cache',
            'route:cache',
            'view:cache',
            'event:cache',
            'optimize',
        ] as $command) {
            try {
                $report['artisan'][$command] = Artisan::call($command);
            } catch (\Throwable $e) {
                report($e);
                $report['errors'][$command] = $e->getMessage();
            }
        }

        $this->setReport('Caches recriados', $report);

        if (empty($report['errors'])) {
            $this->notifySuccess('Caches recriados com sucesso.');
            return;
        }

        Notification::make()
            ->title('Caches recriados com avisos')
            ->body('Alguns comandos retornaram erro. Verifique o relatório na tela.')
            ->warning()
            ->send();
    }

    public function runOptimizeClear(): void
    {
        $report = [
            'artisan' => [],
            'errors' => [],
        ];

        foreach ([
            'optimize:clear',
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'event:clear',
        ] as $command) {
            try {
                $report['artisan'][$command] = Artisan::call($command);
            } catch (\Throwable $e) {
                report($e);
                $report['errors'][$command] = $e->getMessage();
            }
        }

        Cache::put('asset_version', now()->timestamp, now()->addDays(30));

        $this->setReport('Optimize clear executado', $report);
        $this->notifySuccess('Optimize clear executado.');
    }

    public function restartQueue(): void
    {
        try {
            $code = Artisan::call('queue:restart');

            $this->setReport('Workers de fila reiniciados', [
                'artisan' => [
                    'queue:restart' => $code,
                ],
                'queue_driver' => config('queue.default'),
            ]);

            $this->notifySuccess('Sinal de restart enviado para os workers de fila.');
        } catch (\Throwable $e) {
            $this->notifyError('Erro ao reiniciar fila', $e);
        }
    }

    public function createStorageLink(): void
    {
        try {
            $code = Artisan::call('storage:link');

            $this->setReport('Storage link verificado', [
                'artisan' => [
                    'storage:link' => $code,
                ],
                'public_storage_exists' => is_link(public_path('storage')) || file_exists(public_path('storage')),
                'target' => storage_path('app/public'),
            ]);

            $this->notifySuccess('Link de storage verificado/criado.');
        } catch (\Throwable $e) {
            $this->notifyError('Erro ao criar storage link', $e);
        }
    }

    private function availableActions(): array
    {
        return [
            'clear_application_cache' => [
                'title' => 'Limpar cache da aplicação',
                'description' => 'Executa limpeza profunda de cache sem limpar sessões e filas.',
                'danger' => false,
            ],
            'clear_advanced' => [
                'title' => 'Limpeza avançada',
                'description' => 'Limpa caches profundos, views, bootstrap/cache, filas suportadas e renova asset_version.',
                'danger' => true,
            ],
            'clear_sessions' => [
                'title' => 'Limpar sessões',
                'description' => 'Pode desconectar usuários conectados.',
                'danger' => true,
            ],
            'run_optimize_clear' => [
                'title' => 'Optimize clear',
                'description' => 'Executa comandos clear do Laravel e renova asset_version.',
                'danger' => false,
            ],
            'rebuild_caches' => [
                'title' => 'Recriar caches',
                'description' => 'Recompila config, rotas, views, eventos e optimize.',
                'danger' => false,
            ],
            'create_storage_link' => [
                'title' => 'Verificar storage link',
                'description' => 'Executa storage:link para corrigir imagens públicas em /storage.',
                'danger' => false,
            ],
            'restart_queue' => [
                'title' => 'Reiniciar workers',
                'description' => 'Executa queue:restart para reiniciar workers com segurança.',
                'danger' => false,
            ],
        ];
    }

    public function pendingActionTitle(): string
    {
        return $this->availableActions()[$this->pendingAction]['title'] ?? 'Confirmar ação';
    }

    public function pendingActionDescription(): string
    {
        return $this->availableActions()[$this->pendingAction]['description'] ?? 'Informe o PIN administrativo para continuar.';
    }

    public function pendingActionIsDanger(): bool
    {
        return (bool) ($this->availableActions()[$this->pendingAction]['danger'] ?? false);
    }

    private function setReport(string $action, array $report): void
    {
        $this->lastAction = $action;
        $this->lastActionAt = now()->format('d/m/Y H:i:s');
        $this->lastReport = $report;
        $this->adminPin = null;
    }

    private function notifySuccess(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    private function notifyError(string $title, \Throwable $e): void
    {
        report($e);

        Notification::make()
            ->title($title)
            ->body($e->getMessage())
            ->danger()
            ->send();
    }

    public function stats(): array
    {
        return [
            'app_env' => (string) config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'cache_driver' => (string) config('cache.default'),
            'session_driver' => (string) config('session.driver'),
            'queue_driver' => (string) config('queue.default'),
            'storage_link' => is_link(public_path('storage')) || file_exists(public_path('storage')),
            'asset_version' => Cache::get('asset_version'),
            'views_count' => $this->countFiles(storage_path('framework/views')),
            'cache_files_count' => $this->countFiles(storage_path('framework/cache/data')),
            'session_files_count' => $this->countFiles(storage_path('framework/sessions')),
            'bootstrap_cache_count' => $this->countFiles(base_path('bootstrap/cache'), ['.gitignore']),
            'log_size' => $this->formatBytes($this->folderSize(storage_path('logs'))),
        ];
    }

    private function countFiles(string $path, array $ignore = []): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        try {
            return collect(File::allFiles($path))
                ->reject(fn ($file): bool => in_array($file->getFilename(), $ignore, true))
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function folderSize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;

        try {
            foreach (File::allFiles($path) as $file) {
                $size += $file->getSize();
            }
        } catch (\Throwable) {
            return 0;
        }

        return $size;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024) {
                return number_format($value, 2, ',', '.') . ' ' . $unit;
            }

            $value /= 1024;
        }

        return number_format($value, 2, ',', '.') . ' PB';
    }
}