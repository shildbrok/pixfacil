<?php



namespace App\Filament\Pages;

use App\Models\DailyBonusConfig;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AdminDailyBonusConfigPage extends Page
{
    protected static string $view = 'filament.pages.admin-daily-bonus-config-page';

    protected static ?string $title = 'Bônus Diário';
    protected static ?string $navigationLabel = 'Bônus Diário';
    protected static ?string $navigationGroup = 'Operação Financeira';
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'bonus-diario';

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
        $record = $this->record();
        $this->form->fill([
            'bonus_value' => (float) $record->bonus_value,
            'cycle_hours' => (int) $record->cycle_hours,
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
                Forms\Components\Section::make('Configuração do Bônus Diário')
                    ->description('Defina o valor do bônus e o intervalo disponível para resgate pelos usuários.')
                    ->schema([
                        Forms\Components\TextInput::make('bonus_value')
                            ->label('Valor do bônus')
                            ->prefix('R$')
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->helperText('Ex.: 1,00 • 2,50 • 5,00'),

                        Forms\Components\TextInput::make('cycle_hours')
                            ->label('Intervalo de resgate')
                            ->suffix('horas')
                            ->numeric()
                            ->inputMode('numeric')
                            ->minValue(1)
                            ->step(1)
                            ->required()
                            ->helperText('O padrão recomendado é 24 horas.'),

                        Forms\Components\Placeholder::make('preview')
                            ->label('Prévia')
                            ->content(function (Forms\Get $get): string {
                                $value = (float) ($get('bonus_value') ?? 0);
                                $hours = (int) ($get('cycle_hours') ?? 24);
                                $money = $this->money($value);
                                $when = $hours === 24 ? 'uma vez por dia' : "a cada {$hours} horas";
                                return "O usuário poderá resgatar {$money} {$when}.";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Confirmação administrativa')
                    ->schema([
                        Forms\Components\TextInput::make('admin_password')
                            ->label('PIN administrativo')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Boas práticas')
                    ->description('Regras operacionais para evitar abuso.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('tips')
                            ->label('')
                            ->content(
                                "• Intervalos menores que 12 horas aumentam bastante o volume de resgates.\n" .
                                "• Bônus alto com intervalo curto tende a incentivar multi-contas.\n" .
                                "• Regras de KYC, depósito mínimo ou limite por perfil devem ser controladas no backend."
                            )
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        if (! app(AdminActionGuard::class)->confirm((string) ($data['admin_password'] ?? ''))) {
            Notification::make()->title('Acesso negado')->body('PIN administrativo inválido.')->danger()->send();
            return;
        }

        $record = $this->record();
        $before = $record->only(['bonus_value', 'cycle_hours']);
        $record->update([
            'bonus_value' => (float) ($data['bonus_value'] ?? 0),
            'cycle_hours' => (int) ($data['cycle_hours'] ?? 24),
        ]);
        AdminAudit::log(
            'daily_bonus.config.update',
            $record,
            $before,
            $record->fresh()->only(['bonus_value', 'cycle_hours']),
            'Atualizou configuração do bônus diário.'
        );

        Notification::make()
            ->title('Bônus diário atualizado')
            ->body('A configuração foi salva com sucesso.')
            ->success()
            ->send();
    }

    public function record(): DailyBonusConfig
    {
        return DailyBonusConfig::query()->firstOrCreate(
            ['id' => 1],
            ['bonus_value' => 10.00, 'cycle_hours' => 24]
        );
    }

    public function stats(): array
    {
        $record = $this->record();
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $claimsToday = DB::table('daily_bonus_claims')->whereDate('claimed_at', $today)->count();
        $claimsYesterday = DB::table('daily_bonus_claims')->whereDate('claimed_at', $yesterday)->count();
        $totalClaims = DB::table('daily_bonus_claims')->count();
        $lastClaim = DB::table('daily_bonus_claims')->latest('claimed_at')->value('claimed_at');
        $bonusValue = (float) $record->bonus_value;

        return [
            'bonus_value' => $bonusValue,
            'cycle_hours' => (int) $record->cycle_hours,
            'claims_today' => $claimsToday,
            'claims_yesterday' => $claimsYesterday,
            'total_claims' => $totalClaims,
            'cost_today' => $claimsToday * $bonusValue,
            'cost_yesterday' => $claimsYesterday * $bonusValue,
            'cost_total_estimated' => $totalClaims * $bonusValue,
            'last_claim' => $lastClaim,
            'risk_label' => $this->riskLabel((int) $record->cycle_hours, $bonusValue),
        ];
    }

    private function riskLabel(int $hours, float $value): string
    {
        if ($hours < 12 && $value > 0) return 'Alto risco';
        if ($hours < 24 || $value >= 5) return 'Atenção';
        return 'Normal';
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
