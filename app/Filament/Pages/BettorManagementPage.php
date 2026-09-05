<?php



namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BettorManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.bettor-management-page';

    protected static ?string $title = 'Gestão de Apostadores';
    protected static ?string $navigationLabel = 'Apostadores';
    protected static ?string $navigationGroup = 'Operação de Jogadores';
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'gestao-apostadores';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getBettorStats(): array
    {
        $bettorIds = DB::table('orders')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $totalBettors = $bettorIds->count();

        $totalBet = (float) DB::table('orders')
            ->where('type', 'bet')
            ->sum('amount');

        $totalWin = (float) DB::table('orders')
            ->where('type', 'win')
            ->sum('amount');

        $totalDeposited = (float) DB::table('deposits')
            ->where('status', 1)
            ->whereIn('user_id', $bettorIds)
            ->sum('amount');

        $activeToday = DB::table('orders')
            ->whereDate('created_at', now()->toDateString())
            ->distinct('user_id')
            ->count('user_id');

        return [
            'bettors' => $totalBettors,
            'total_bet' => $totalBet,
            'total_win' => $totalWin,
            'total_loss' => max(0, $totalBet - $totalWin),
            'total_deposited' => $totalDeposited,
            'active_today' => $activeToday,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->bettorQuery())
            ->defaultSort('last_activity_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Apostador')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->copyable()
                    ->copyMessage('Apostador copiado.'),

                TextColumn::make('bet_volume')
                    ->label('Montante apostado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->description('Soma das apostas'),

                TextColumn::make('win_volume')
                    ->label('Montante de ganho')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('loss_volume')
                    ->label('Montante de perda')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->description('Apostado - ganho'),

                TextColumn::make('total_deposited')
                    ->label('Montante depositado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('house_frequency')
                    ->label('Frequência na casa')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Diário' => 'success',
                        'Frequente' => 'info',
                        'Compulsivo' => 'danger',
                        'Recorrente' => 'warning',
                        'Ocasional' => 'gray',
                        'Inativo' => 'gray',
                        default => 'gray',
                    })
                    ->state(fn (User $record) => $this->classifyFrequency($record)),

                TextColumn::make('activity_days')
                    ->label('Dias ativos')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->description('Últimos 30 dias'),

                TextColumn::make('activity_events')
                    ->label('Eventos')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description('Orders + depósitos + bônus diário'),

                TextColumn::make('last_activity_at')
                    ->label('Última atividade')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->last_activity_at ? \Carbon\Carbon::parse($record->last_activity_at)->diffForHumans() : null),

                TextColumn::make('created_at')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_bets')
                    ->label('Somente com apostas')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("(select count(*) from orders where orders.user_id = users.id) > 0")),

                Filter::make('has_deposits')
                    ->label('Somente depositantes')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) > 0")),

                Filter::make('activity_period')
                    ->label('Período de atividade')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['from'] ?? null) && ! ($data['until'] ?? null)) {
                            return $query;
                        }

                        return $query->whereExists(function ($sub) use ($data) {
                            $sub->selectRaw('1')
                                ->from('orders')
                                ->whereColumn('orders.user_id', 'users.id')
                                ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '>=', $date))
                                ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '<=', $date));
                        });
                    }),

                Filter::make('big_bettors')
                    ->label('Grandes apostadores')
                    ->form([
                        Forms\Components\TextInput::make('min_bet')
                            ->label('Mínimo apostado')
                            ->numeric()
                            ->prefix('R$')
                            ->placeholder('Ex.: 1000'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $min = (float) ($data['min_bet'] ?? 0);

                        if ($min <= 0) {
                            return $query;
                        }

                        return $query->whereRaw(
                            "(select coalesce(sum(amount), 0) from orders where orders.user_id = users.id and orders.type = 'bet') >= ?",
                            [$min]
                        );
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->actions([
                Action::make('view_summary')
                    ->label('Resumo')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalHeading(fn (User $record) => 'Resumo do apostador: ' . ($record->name ?: $record->email))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (User $record) => view('filament.pages.partials.bettor-summary-modal', [
                        'record' => $record,
                        'money' => fn ($value) => $this->money($value),
                        'frequency' => $this->classifyFrequency($record),
                    ])),
            ])
            ->emptyStateHeading('Nenhum apostador encontrado')
            ->emptyStateDescription('Quando houver apostas em orders, os apostadores aparecerão aqui.');
    }

    private function bettorQuery(): Builder
    {
        return User::query()
            ->with('wallet')
            ->select('users.*')
            ->selectSub(
                "select coalesce(sum(amount), 0) from orders where orders.user_id = users.id and orders.type = 'bet'",
                'bet_volume'
            )
            ->selectSub(
                "select coalesce(sum(amount), 0) from orders where orders.user_id = users.id and orders.type = 'win'",
                'win_volume'
            )
            ->selectSub(
                "greatest(
                    (select coalesce(sum(amount), 0) from orders where orders.user_id = users.id and orders.type = 'bet') -
                    (select coalesce(sum(amount), 0) from orders where orders.user_id = users.id and orders.type = 'win'),
                    0
                )",
                'loss_volume'
            )
            ->selectSub(
                "select coalesce(sum(amount), 0) from deposits where deposits.user_id = users.id and deposits.status = 1",
                'total_deposited'
            )
            ->selectSub(
                "select count(distinct date(activity_date)) from (
                    select orders.user_id as user_id, orders.created_at as activity_date from orders
                    union all
                    select transactions.user_id as user_id, transactions.created_at as activity_date from transactions
                    union all
                    select daily_bonus_claims.user_id as user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims
                ) as activity where activity.user_id = users.id and activity.activity_date >= date_sub(now(), interval 30 day)",
                'activity_days'
            )
            ->selectSub(
                "select count(*) from (
                    select orders.user_id as user_id, orders.created_at as activity_date from orders
                    union all
                    select transactions.user_id as user_id, transactions.created_at as activity_date from transactions
                    union all
                    select daily_bonus_claims.user_id as user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims
                ) as activity where activity.user_id = users.id and activity.activity_date >= date_sub(now(), interval 30 day)",
                'activity_events'
            )
            ->selectSub(
                "select max(activity_date) from (
                    select orders.user_id as user_id, orders.created_at as activity_date from orders
                    union all
                    select transactions.user_id as user_id, transactions.created_at as activity_date from transactions
                    union all
                    select daily_bonus_claims.user_id as user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims
                ) as activity where activity.user_id = users.id",
                'last_activity_at'
            )
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'users.id');
            });
    }

    public function classifyFrequency(User $record): string
    {
        $days = (int) ($record->activity_days ?? 0);
        $events = (int) ($record->activity_events ?? 0);
        $betVolume = (float) ($record->bet_volume ?? 0);

        if ($days >= 25 || $events >= 500) {
            return 'Compulsivo';
        }

        if ($days >= 20) {
            return 'Diário';
        }

        if ($days >= 10 || $events >= 120) {
            return 'Frequente';
        }

        if ($days >= 4 || $events >= 30 || $betVolume >= 1000) {
            return 'Recorrente';
        }

        if ($days >= 1) {
            return 'Ocasional';
        }

        return 'Inativo';
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}