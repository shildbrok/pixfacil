<?php



namespace App\Filament\Pages;

use App\Models\BetcrmSetting;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AdminBetCrmPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.admin-betcrm-page';

    protected static ?string $title = 'Integração BetCRM';
    protected static ?string $navigationLabel = 'Integração BetCRM';
    protected static ?string $navigationGroup = 'Configurações da Plataforma';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?int $navigationSort = 8;
    protected static ?string $slug = 'integracao-betcrm';

    public ?array $data = [];

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
        $s = BetcrmSetting::current();

        $this->form->fill([
            'enabled'           => (bool) $s->enabled,
            'site'              => $s->site,
            'client_id'         => $s->client_id,
            'client_secret'     => $s->client_secret, 
            'send_registration' => (bool) $s->send_registration,
            'send_login'        => (bool) $s->send_login,
            'send_app_install'  => (bool) $s->send_app_install,
            'send_deposit'      => (bool) $s->send_deposit,
            'send_withdrawal'   => (bool) $s->send_withdrawal,
        ]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Conexão')
                    ->description('Credenciais do painel do BetCRM (em Credenciais). O segredo é guardado criptografado.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Integração ativa')
                            ->helperText('Quando desligado, nenhum evento é enviado.')
                            ->columnSpanFull(),

                        TextInput::make('client_id')
                            ->label('X-Client-Id')
                            ->maxLength(255),

                        TextInput::make('client_secret')
                            ->label('X-Client-Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Deixe como está para manter. Apagar o campo remove o segredo.'),

                        TextInput::make('site')
                            ->label('Site (marca)')
                            ->maxLength(120)
                            ->helperText('Identifica esta marca no BetCRM e faz parte da identidade do lead. NÃO mude depois de integrado: outro valor cria leads duplicados. Vazio = derivado da APP_URL.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Quais eventos enviar')
                    ->schema([
                        Toggle::make('send_registration')->label('Cadastro'),
                        Toggle::make('send_login')->label('Login'),
                        Toggle::make('send_app_install')->label('Instalação de app/PWA'),
                        Toggle::make('send_deposit')->label('Depósito'),
                        Toggle::make('send_withdrawal')->label('Saque'),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        if (config('app.demo')) {
            Notification::make()
                ->title('Atenção')
                ->body('Você não pode realizar esta alteração na versão demo.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $s = BetcrmSetting::current();
        $s->update($data);

        Notification::make()
            ->title('Integração BetCRM salva')
            ->body('As configurações foram atualizadas.')
            ->success()
            ->send();

        $this->mount();
    }
}