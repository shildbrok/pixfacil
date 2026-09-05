<?php



namespace App\Filament\Pages;

use Filament\Pages\Page;

class DashboardAdmin extends Page
{
    protected static string $view = 'filament.pages.dashboard-admin';

    protected static ?string $title = 'Visão Geral';
    protected static ?string $navigationLabel = 'Visão Geral';
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Principal';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'visao-geral';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function projectLinks(): array
    {
        return [
            [
                'label' => 'Telegram do desenvolvedor',
                'url' => 'https://t.me/Joao_igaming',
                'description' => 'Contato direto: João (DEV) — @Joao_igaming.',
            ],
            [
                'label' => 'Canal no Telegram',
                'url' => 'https://t.me/central_igaming_channel',
                'description' => 'Central iGaming — canal oficial de atualizações.',
            ],
            [
                'label' => 'Grupo no Telegram',
                'url' => 'https://t.me/central_igaming',
                'description' => 'Central iGaming — comunidade.',
            ],
            [
                'label' => 'Canal no YouTube',
                'url' => 'https://www.youtube.com/@centraligaming',
                'description' => 'Central iGaming — conteúdos e vídeos.',
            ],
        ];
    }
}