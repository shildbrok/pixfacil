<?php



namespace App\Filament\Pages;

use App\Models\Game;
use App\Models\GamesKey;
use App\Models\Provider;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class AdminGameAggregatorPage extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $view = 'filament.pages.admin-game-aggregator-page';

    protected static ?string $title = 'Agregador de Jogos';
    protected static ?string $navigationLabel = 'Agregador de Jogos';
    protected static ?string $navigationGroup = 'Configurações da Plataforma';
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'agregador-de-jogos';

    public ?array $data = [];
    public ?GamesKey $setting = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(): void
    {
        $this->setting = GamesKey::query()->first();

        $this->form->fill([
            'playfiver_url' => $this->setting?->playfiver_url ?: 'https://api.playfivers.com',
            'playfiver_code' => '',
            'playfiver_token' => '',
            'playfiver_secret' => '',
            'playfiver_webhook_url' => $this->webhookUrl('/playfiver/webhook'),
        ]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function saveAggregatorAction(): Action
    {
        return Action::make('saveAggregator')
            ->label('Salvar agregador')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->modalHeading('Confirmar alteração do agregador')
            ->modalDescription('Informe o PIN administrativo para salvar as credenciais da PlayFiver.')
            ->modalSubmitActionLabel('Confirmar e salvar')
            ->form([
                TextInput::make('admin_password')
                    ->label('PIN administrativo')
                    ->placeholder('Digite o PIN de 6 dígitos')
                    ->password()
                    ->numeric()
                    ->length(6)
                    ->required(),
            ])
            ->action(fn (array $data) => $this->submit($data));
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Tabs::make('Agregador')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tabs\Tab::make('PlayFiver')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Section::make('Informações rápidas')
                                    ->description('Credenciais usadas para abrir jogos, sincronizar catálogo, consultar carteiras e receber eventos do agregador.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('playfiver_links')
                                            ->label('Links úteis')
                                            ->content(new HtmlString('
                                                <div style="display:flex;gap:10px;flex-wrap:wrap">
                                                    <a href="https://playfiver.app" target="_blank" style="display:inline-flex;align-items:center;justify-content:center;padding:9px 13px;border-radius:12px;background:#00b91e;color:#fff;font-weight:800;font-size:13px;text-decoration:none">Painel PlayFiver</a>
                                                    <a href="https://t.me/playfivers" target="_blank" style="display:inline-flex;align-items:center;justify-content:center;padding:9px 13px;border-radius:12px;background:#0ea5e9;color:#fff;font-weight:800;font-size:13px;text-decoration:none">Suporte Telegram</a>
                                                </div>
                                            ')),
                                    ]),

                                Section::make('Webhook PlayFiver')
                                    ->description('Cadastre esta URL no painel da PlayFiver para receber Balance, WinBet e Refund.')
                                    ->schema([
                                        TextInput::make('playfiver_webhook_url')
                                            ->label('Webhook PlayFiver')
                                            ->default(fn () => $this->webhookUrl('/playfiver/webhook'))
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->helperText('Cole exatamente esta URL no painel da PlayFiver.')
                                            ->suffixAction($this->copyAction('copy_playfiver_webhook', $this->webhookUrl('/playfiver/webhook'))),
                                    ]),

                                Section::make('Credenciais PlayFiver')
                                    ->description('Segredos existentes nunca são exibidos. Deixe o campo vazio para manter o valor atual.')
                                    ->schema([
                                        TextInput::make('playfiver_url')
                                            ->label('API Base URL')
                                            ->placeholder('https://api.playfivers.com')
                                            ->maxLength(191)
                                            ->columnSpanFull()
                                            ->helperText('Usada por sincronização de catálogo e consulta de carteiras. Se estiver vazio, o sistema usa https://api.playfivers.com.'),

                                        TextInput::make('playfiver_code')
                                            ->label('Código do Agente')
                                            ->placeholder('Digite o código do agente')
                                            ->maxLength(191)
                                            ->helperText(fn (): string => filled($this->setting?->playfiver_code) ? 'Código já configurado. Digite apenas para substituir.' : 'Ainda não configurado.'),

                                        TextInput::make('playfiver_token')
                                            ->label('Token do Agente')
                                            ->placeholder('Digite o token do agente')
                                            ->maxLength(191)
                                            ->password()
                                            ->helperText(fn (): string => filled($this->setting?->playfiver_token) ? 'Token já configurado. Digite apenas para substituir.' : 'Ainda não configurado.')
                                            ->extraInputAttributes([
                                                'autocomplete' => 'off',
                                                'spellcheck' => 'false',
                                                'autocapitalize' => 'off',
                                                'autocorrect' => 'off',
                                            ]),

                                        TextInput::make('playfiver_secret')
                                            ->label('Chave Secreta do Agente')
                                            ->placeholder('Digite a chave secreta do agente')
                                            ->maxLength(191)
                                            ->password()
                                            ->helperText(fn (): string => filled($this->setting?->playfiver_secret) ? 'Segredo já configurado. Digite apenas para substituir.' : 'Ainda não configurado.')
                                            ->extraInputAttributes([
                                                'autocomplete' => 'off',
                                                'spellcheck' => 'false',
                                                'autocapitalize' => 'off',
                                                'autocorrect' => 'off',
                                            ]),
                                    ])
                                    ->columns(3),
                            ]),
                    ]),
            ]);
    }

    private function copyAction(string $name, string $value): FormAction
    {
        return FormAction::make($name)
            ->label('Copiar')
            ->icon('heroicon-m-clipboard')
            ->extraAttributes([
                'x-on:click' => 'navigator.clipboard.writeText(' . json_encode($value) . ')',
            ])
            ->action(function () use ($value): void {
                Notification::make()->title('Copiado')->body($value)->success()->send();
            });
    }

    public function submit(array $confirmation = []): void
    {
        try {
            if (config('app.demo')) {
                Notification::make()->title('Modo demonstração')->body('Esta alteração não pode ser realizada no modo demonstração.')->danger()->send();
                return;
            }

            if (! app(AdminActionGuard::class)->confirm((string) ($confirmation['admin_password'] ?? ''))) {
                Notification::make()->title('Acesso negado')->body('O PIN administrativo está incorreto.')->danger()->send();
                return;
            }

            $payload = $this->form->getState();
            unset($payload['playfiver_webhook_url']);
            $payload['playfiver_url'] = rtrim((string) ($payload['playfiver_url'] ?: 'https://api.playfivers.com'), '/');

            $setting = GamesKey::query()->first();
            $before = [
                'playfiver_url' => $setting?->playfiver_url,
                'code_configured' => filled($setting?->playfiver_code),
                'token_configured' => filled($setting?->playfiver_token),
                'secret_configured' => filled($setting?->playfiver_secret),
            ];

            foreach (['playfiver_code', 'playfiver_token', 'playfiver_secret'] as $secretField) {
                if (! filled($payload[$secretField] ?? null)) {
                    unset($payload[$secretField]);
                }
            }

            if ($setting) {
                $setting->update($payload);
            } else {
                $setting = GamesKey::query()->create($payload);
            }

            AdminAudit::log('playfiver.credentials.update', $setting, $before, [
                'playfiver_url' => $setting->fresh()->playfiver_url,
                'code_configured' => filled($setting->fresh()->playfiver_code),
                'token_configured' => filled($setting->fresh()->playfiver_token),
                'secret_configured' => filled($setting->fresh()->playfiver_secret),
            ], 'Atualizou configuração do agregador PlayFiver sem registrar segredos.');

            Notification::make()->title('Agregador atualizado')->body('As credenciais da PlayFiver foram salvas com sucesso.')->success()->send();
            $this->mount();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Erro ao salvar agregador')->body($e->getMessage())->danger()->send();
        }
    }

    public function stats(): array
    {
        $setting = $this->setting ?? GamesKey::query()->first();

        return [
            'configured' => filled($setting?->playfiver_code) && filled($setting?->playfiver_token) && filled($setting?->playfiver_secret),
            'api_url' => $setting?->playfiver_url ?: 'https://api.playfivers.com',
            'providers' => Provider::query()->count(),
            'games' => Game::query()->count(),
            'last_update' => $setting?->updated_at,
        ];
    }

    public function webhookUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim(url('/'), '/');
        }
        return $baseUrl . '/' . ltrim($path, '/');
    }
}
