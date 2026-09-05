<?php



namespace App\Filament\Pages;

use App\Models\KycConfig;
use App\Models\Verificacao;
use App\Support\AdminActionGuard;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AdminKycSettingsPage extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $view = 'filament.pages.admin-kyc-settings-page';

    protected static ?string $title = 'Configurações de KYC';
    protected static ?string $navigationLabel = 'Configurações de KYC';
    protected static ?string $navigationGroup = 'KYC e Compliance';
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'configuracoes-de-kyc';

    public ?array $data = [];
    public ?KycConfig $config = null;

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()?->can('admin') ?? false);
    }

    public static function canView(): bool
    {
        return auth()->check() && (auth()->user()?->can('admin') ?? false);
    }

    public function mount(): void
    {
        $this->config = KycConfig::current();

        $this->form->fill([
            'withdrawal_kyc_required' => (bool) $this->config->withdrawal_kyc_required,
            'auto_approve_documents' => (bool) $this->config->auto_approve_documents,
            'is_active' => (bool) $this->config->is_active,
            'notes' => $this->config->notes,
        ]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function saveKycSettingsAction(): Action
    {
        return Action::make('saveKycSettings')
            ->label('Salvar configurações')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->modalHeading('Confirmar alteração do KYC')
            ->modalDescription('Informe o PIN administrativo para salvar as regras de KYC.')
            ->modalSubmitActionLabel('Confirmar e salvar')
            ->form([
                Forms\Components\TextInput::make('admin_password')
                    ->label('PIN administrativo')
                    ->placeholder('Digite o PIN de 6 dígitos')
                    ->password()
                    ->numeric()
                    ->length(6)
                    ->required(),
            ])
            ->action(fn (array $data) => $this->save($data));
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Tabs::make('KYC')
                    ->persistTabInQueryString()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Regras')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Regras principais')
                                    ->description('Controle quando o KYC será exigido e como os documentos enviados serão tratados.')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Configuração ativa')
                                            ->helperText('Mantém esta configuração como a regra KYC ativa do sistema.')
                                            ->default(true)
                                            ->inline(false)
                                            ->onColor('success')
                                            ->offColor('danger'),

                                        Toggle::make('withdrawal_kyc_required')
                                            ->label('KYC obrigatório para saque')
                                            ->helperText('Se ativo, o cliente só poderá sacar quando possuir KYC aprovado.')
                                            ->live()
                                            ->inline(false)
                                            ->onColor('success')
                                            ->offColor('danger'),

                                        Toggle::make('auto_approve_documents')
                                            ->label('Aprovar documentos automaticamente')
                                            ->helperText('Use com cuidado. Se ativo, novos envios podem ser aprovados automaticamente.')
                                            ->visible(fn (callable $get): bool => (bool) $get('withdrawal_kyc_required'))
                                            ->inline(false)
                                            ->onColor('warning')
                                            ->offColor('gray'),
                                    ])
                                    ->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Observações')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Anotações internas')
                                    ->description('Use para registrar instruções operacionais, regras internas ou observações para a equipe.')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações')
                                            ->rows(6)
                                            ->maxLength(65535)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function save(array $confirmation = []): void
    {
        if (! app(AdminActionGuard::class)->confirm((string) ($confirmation['admin_password'] ?? ''))) {
            Notification::make()
                ->title('Acesso negado')
                ->body('O PIN administrativo está incorreto.')
                ->danger()
                ->send();

            return;
        }

        try {
            $state = $this->form->getState();

            $config = KycConfig::current();

            $config->fill([
                'withdrawal_kyc_required' => (bool) ($state['withdrawal_kyc_required'] ?? false),
                'auto_approve_documents' => (bool) ($state['auto_approve_documents'] ?? false),
                'is_active' => (bool) ($state['is_active'] ?? true),
                'notes' => $state['notes'] ?? null,
            ]);

            $config->save();

            Notification::make()
                ->title('Configuração KYC salva')
                ->body('As regras de KYC e compliance foram atualizadas.')
                ->success()
                ->send();

            $this->mount();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao salvar KYC')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function stats(): array
    {
        $config = $this->config ?? KycConfig::current();

        $base = Verificacao::query();

        return [
            'active' => (bool) $config->is_active,
            'withdrawal_required' => (bool) $config->withdrawal_kyc_required,
            'auto_approve' => (bool) $config->auto_approve_documents,
            'pending' => (clone $base)->where('status', Verificacao::STATUS_PENDING)->count(),
            'approved' => (clone $base)->where('status', Verificacao::STATUS_APPROVED)->count(),
            'rejected' => (clone $base)->where('status', Verificacao::STATUS_REJECTED)->count(),
            'total' => (clone $base)->count(),
            'updated_at' => $config->updated_at,
        ];
    }
}
