<?php



namespace App\Filament\Pages;

use App\Models\Order;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BetHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.bet-history-page';

    protected static ?string $title = 'Histórico de Apostas';
    protected static ?string $navigationLabel = 'Histórico de Apostas';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'historico-apostas';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getStats(): array
    {
        $query = Order::query();
        $totalBet = (float) (clone $query)->where('type', 'bet')->sum('amount');
        $totalWin = (float) (clone $query)->where('type', 'win')->sum('amount');

        return [
            'total' => (clone $query)->count(),
            'bet_count' => (clone $query)->where('type', 'bet')->count(),
            'win_count' => (clone $query)->where('type', 'win')->count(),
            'total_bet' => $totalBet,
            'total_win' => $totalWin,
            'house_result' => $totalBet - $totalWin,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->historyQuery())
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Usuário')->searchable()->sortable()->weight('bold')
                    ->description(fn ($record) => $record->user?->email),
                TextColumn::make('game')->label('Jogo')->searchable()->sortable()->limit(28)->tooltip(fn ($record) => $record->game),
                TextColumn::make('round_id')->label('Rodada')->searchable()->copyable()->limit(20)->toggleable(),
                TextColumn::make('transaction_id')->label('Transação')->searchable()->copyable()->limit(20)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Resultado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        'bet' => 'Perda / aposta',
                        'win' => 'Ganho',
                        'refund' => 'Estorno',
                        default => (string) $state,
                    })
                    ->color(fn ($state) => match ((string) $state) {
                        'bet' => 'danger',
                        'win' => 'success',
                        'refund' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('type_money')->label('Carteira')->badge()->sortable()->toggleable(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->description(fn ($record) => (string) $record->type === 'bet' ? 'valor debitado' : ((string) $record->type === 'win' ? 'valor creditado' : null))
                    ->sortable(),
                TextColumn::make('providers')->label('Provedor')->badge()->sortable()->toggleable(),
                IconColumn::make('refunded')->label('Estornado')->boolean()->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Filter::make('user')
                    ->label('Usuário')
                    ->form([
                        Forms\Components\TextInput::make('search')->label('Nome, e-mail ou CPF'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['search'] ?? ''));
                        if ($search === '') return $query;
                        return $query->whereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%");
                        });
                    }),
                SelectFilter::make('type')
                    ->label('Resultado')
                    ->options(['bet' => 'Perda / aposta', 'win' => 'Ganho', 'refund' => 'Estorno']),
                SelectFilter::make('providers')
                    ->label('Provedor')
                    ->options(fn (): array => Order::query()
                        ->whereNotNull('providers')
                        ->distinct()
                        ->orderBy('providers')
                        ->pluck('providers', 'providers')
                        ->toArray()),
                Filter::make('created_at')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            // Ledger/recompensas são imutáveis: a tela é somente leitura.
            ->emptyStateHeading('Nenhum registro encontrado')
            ->emptyStateDescription('Este histórico ainda não possui registros para os filtros atuais.');
    }

    private function historyQuery(): Builder
    {
        return Order::query()->with('user');
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
