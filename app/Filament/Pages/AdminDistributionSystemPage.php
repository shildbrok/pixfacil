<?php



namespace App\Filament\Pages;

use App\Models\DistributionSystem;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AdminDistributionSystemPage extends Page
{
    protected static string $view = 'filament.pages.admin-distribution-system-page';

    protected static ?string $title = 'Distribuição de Ganhos';
    protected static ?string $navigationLabel = 'Distribuição de Ganhos';
    protected static ?string $navigationGroup = 'Operação Financeira';
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'distribuicao-de-ganhos';

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
            'ativo' => (bool) $record->ativo,
            'modo' => $record->modo ?: 'arrecadacao',
            'meta_arrecadacao' => (float) $record->meta_arrecadacao,
            'percentual_distribuicao' => (float) $record->percentual_distribuicao,
            'rtp_arrecadacao' => (float) $record->rtp_arrecadacao,
            'rtp_distribuicao' => (float) $record->rtp_distribuicao,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Status do sistema')
                    ->description('Controle se a distribuição está ativa e qual modo operacional está em uso.')
                    ->schema([
                        Forms\Components\Toggle::make('ativo')
                            ->label('Sistema ativo')
                            ->helperText('Quando ativo, o sistema usa as regras abaixo para alternar arrecadação/distribuição.')
                            ->inline(false),

                        Forms\Components\Select::make('modo')
                            ->label('Modo atual')
                            ->options([
                                'arrecadacao' => 'Arrecadação',
                                'distribuicao' => 'Distribuição',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Metas e distribuição')
                    ->description('Meta financeira e percentual destinado à distribuição de ganhos.')
                    ->schema([
                        Forms\Components\TextInput::make('meta_arrecadacao')
                            ->label('Meta de arrecadação')
                            ->numeric()
                            ->prefix('R$')
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('percentual_distribuicao')
                            ->label('Percentual de distribuição')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('RTP por modo')
                    ->description('Defina o RTP aplicado em cada fase operacional.')
                    ->schema([
                        Forms\Components\TextInput::make('rtp_arrecadacao')
                            ->label('RTP em arrecadação')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),

                        Forms\Components\TextInput::make('rtp_distribuicao')
                            ->label('RTP em distribuição')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->record()->update([
            'ativo' => ! empty($data['ativo']),
            'modo' => $data['modo'] ?? 'arrecadacao',
            'meta_arrecadacao' => (float) ($data['meta_arrecadacao'] ?? 0),
            'percentual_distribuicao' => (float) ($data['percentual_distribuicao'] ?? 0),
            'rtp_arrecadacao' => (float) ($data['rtp_arrecadacao'] ?? 0),
            'rtp_distribuicao' => (float) ($data['rtp_distribuicao'] ?? 0),
        ]);

        Notification::make()
            ->title('Distribuição atualizada')
            ->body('As configurações foram salvas com sucesso.')
            ->success()
            ->send();
    }

    public function toggleActive(): void
    {
        $record = $this->record();

        $record->update([
            'ativo' => ! (bool) $record->ativo,
        ]);

        $this->form->fill([
            ...$this->form->getState(),
            'ativo' => (bool) $record->fresh()->ativo,
        ]);

        Notification::make()
            ->title($record->fresh()->ativo ? 'Sistema ativado' : 'Sistema desativado')
            ->success()
            ->send();
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['arrecadacao', 'distribuicao'], true)) {
            return;
        }

        $this->record()->update([
            'modo' => $mode,
        ]);

        $this->form->fill([
            ...$this->form->getState(),
            'modo' => $mode,
        ]);

        Notification::make()
            ->title('Modo atualizado')
            ->body($mode === 'arrecadacao' ? 'Modo alterado para arrecadação.' : 'Modo alterado para distribuição.')
            ->success()
            ->send();
    }

    public function resetCycle(): void
    {
        $this->record()->update([
            'total_arrecadado' => 0,
            'total_distribuido' => 0,
            'modo' => 'arrecadacao',
            'start_cycle_at' => now(),
        ]);

        $this->form->fill([
            ...$this->form->getState(),
            'modo' => 'arrecadacao',
        ]);

        Notification::make()
            ->title('Ciclo reiniciado')
            ->body('Totais foram zerados e o modo voltou para arrecadação.')
            ->success()
            ->send();
    }

    public function record(): DistributionSystem
    {
        return DistributionSystem::query()->firstOrCreate(
            ['id' => 1],
            [
                'meta_arrecadacao' => 0,
                'percentual_distribuicao' => 0,
                'rtp_arrecadacao' => 0,
                'rtp_distribuicao' => 0,
                'total_arrecadado' => 0,
                'total_distribuido' => 0,
                'modo' => 'arrecadacao',
                'ativo' => false,
                'start_cycle_at' => now(),
            ]
        );
    }

    public function stats(): array
    {
        $record = $this->record();

        $meta = (float) $record->meta_arrecadacao;
        $totalArrecadado = (float) $record->total_arrecadado;
        $totalDistribuido = (float) $record->total_distribuido;
        $percentualDistribuicao = (float) $record->percentual_distribuicao;

        $progresso = $meta > 0 ? min(100, ($totalArrecadado / $meta) * 100) : 0;
        $valorPrevistoDistribuicao = ($totalArrecadado * $percentualDistribuicao) / 100;
        $saldoDistribuicao = max(0, $valorPrevistoDistribuicao - $totalDistribuido);

        $betsToday = (float) DB::table('orders')
            ->where('type', 'bet')
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $winsToday = (float) DB::table('orders')
            ->where('type', 'win')
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        return [
            'ativo' => (bool) $record->ativo,
            'modo' => $record->modo,
            'meta_arrecadacao' => $meta,
            'percentual_distribuicao' => $percentualDistribuicao,
            'rtp_arrecadacao' => (float) $record->rtp_arrecadacao,
            'rtp_distribuicao' => (float) $record->rtp_distribuicao,
            'total_arrecadado' => $totalArrecadado,
            'total_distribuido' => $totalDistribuido,
            'progresso' => $progresso,
            'valor_previsto_distribuicao' => $valorPrevistoDistribuicao,
            'saldo_distribuicao' => $saldoDistribuicao,
            'start_cycle_at' => $record->start_cycle_at,
            'bets_today' => $betsToday,
            'wins_today' => $winsToday,
            'house_today' => $betsToday - $winsToday,
        ];
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    public function percent($value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }
}
