<?php



namespace App\Filament\Pages;

use App\Support\AdminActionGuard;
use App\Support\AdminLogClassifier;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class LaravelLogsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Logs Laravel';
    protected static ?string $title = 'Logs Laravel';
    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.laravel-logs-page';

    public string $file = 'laravel.log';
    public string $search = '';
    public string $levelFilter = 'errors';
    public int $limit = 300;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Atualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => null),

            Actions\Action::make('clear_current')
                ->label('Limpar log atual')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    TextInput::make('password')
                        ->label('PIN administrativo')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (! app(AdminActionGuard::class)->confirm((string) ($data['password'] ?? ''))) {
                        Notification::make()->title('Acesso negado')->body('PIN administrativo inválida.')->danger()->send();
                        return;
                    }

                    $path = $this->selectedLogPath();
                    if (! $path || ! File::exists($path)) {
                        Notification::make()->title('Log não encontrado')->danger()->send();
                        return;
                    }

                    File::put($path, '');
                    Notification::make()->title('Log limpo')->body('O arquivo de log atual foi esvaziado.')->success()->send();
                }),

            Actions\Action::make('clear_all')
                ->label('Limpar todos os logs')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    TextInput::make('password')
                        ->label('PIN administrativo')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (! app(AdminActionGuard::class)->confirm((string) ($data['password'] ?? ''))) {
                        Notification::make()->title('Acesso negado')->body('PIN administrativo inválida.')->danger()->send();
                        return;
                    }

                    foreach ($this->availableLogPaths() as $path) {
                        File::put($path, '');
                    }

                    Notification::make()->title('Logs limpos')->body('Todos os arquivos laravel*.log foram esvaziados.')->success()->send();
                }),
        ];
    }

    public function getLogFilesProperty(): array
    {
        $files = [];
        foreach ($this->availableLogPaths() as $path) {
            $files[] = basename($path);
        }

        return $files ?: ['laravel.log'];
    }

    public function getRowsProperty(): array
    {
        $path = $this->selectedLogPath();
        if (! $path || ! File::exists($path)) {
            return [];
        }

        $lines = $this->tail($path, max(50, min($this->limit, 2000)));
        $search = trim(mb_strtolower($this->search));

        if ($search !== '') {
            $lines = array_values(array_filter($lines, fn ($line) => str_contains(mb_strtolower($line), $search)));
        }

        $rows = array_map(function (string $line): array {
            $level = $this->detectLevel($line);
            $explain = AdminLogClassifier::explain($line, $level);

            return [
                'line' => $line,
                'label' => $explain['label'],
                'hint' => $explain['hint'],
                'level' => $level,
            ];
        }, $lines);

        return array_values(array_filter($rows, fn (array $row) => $this->shouldShowLevel($row['level'])));
    }

    public function getSelectedFileSizeProperty(): string
    {
        $path = $this->selectedLogPath();
        if (! $path || ! File::exists($path)) {
            return '0 KB';
        }

        $bytes = File::size($path);
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }

        return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    }

    private function availableLogPaths(): array
    {
        return collect(File::glob(storage_path('logs/laravel*.log')) ?: [])
            ->filter(fn ($path) => is_file($path))
            ->sortDesc()
            ->values()
            ->all();
    }

    private function selectedLogPath(): ?string
    {
        $filename = basename($this->file ?: 'laravel.log');
        $path = storage_path('logs/' . $filename);

        if (File::exists($path)) {
            return $path;
        }

        $paths = $this->availableLogPaths();
        return $paths[0] ?? storage_path('logs/laravel.log');
    }

    private function tail(string $path, int $lines): array
    {
        $content = @file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($content)) {
            return [];
        }

        return array_slice($content, -$lines);
    }

    private function shouldShowLevel(string $level): bool
    {
        return match ($this->levelFilter) {
            'all' => true,
            'warnings' => in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING'], true),
            'errors' => in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'], true),
            default => in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'], true),
        };
    }

    private function detectLevel(string $line): string
    {
        foreach (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'] as $level) {
            if (str_contains($line, '.' . $level . ':') || str_contains($line, ' ' . $level . ':')) {
                return $level;
            }
        }

        return 'LOG';
    }
}