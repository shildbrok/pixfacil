<?php



namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmBettorsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.crm-bettors-page';

    protected static ?string $title = 'Apostadores CRM';
    protected static ?string $navigationLabel = 'Apostadores';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'crm-apostadores';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->crmQuery())
            ->defaultSort('last_activity_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->columns([
                TextColumn::make('name')->label('Cliente')->searchable()->sortable()->weight('bold')->description(fn (User $record) => $record->email)->copyable(),
                TextColumn::make('phone')->label('Telefone')->searchable()->placeholder('-')->copyable(),
                TextColumn::make('cpf')->label('CPF')->searchable()->placeholder('-')->copyable(),
                TextColumn::make('total_deposited')->label('Depositado')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('total_withdrawn')->label('Sacado')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('bet_volume')->label('Apostado')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('win_volume')->label('Ganho')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('loss_volume')->label('Perda')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('house_result')->label('Resultado casa')->formatStateUsing(fn ($state) => $this->money($state))->sortable()->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('wallet_total')->label('Saldo')->formatStateUsing(fn ($state) => $this->money($state))->sortable()->toggleable(),
                TextColumn::make('profile')->label('Perfil')->badge()->state(fn (User $record) => $this->classifyClient($record))->color(fn (string $state): string => match($state) {
                    'VIP' => 'warning', 'Apostador alto' => 'danger', 'Risco de abandono' => 'danger', 'Recorrente' => 'info', 'Depositante' => 'success', 'Influenciador' => 'purple', default => 'gray',
                }),
                TextColumn::make('affiliate')->label('Afiliado')->state(fn (User $record) => $record->inviter ? (User::find($record->inviter)?->email ?: $record->inviter) : '-')->toggleable(),
                IconColumn::make('is_influencer')->label('Influencer')->boolean()->toggleable(),
                TextColumn::make('last_activity_at')->label('Última atividade')->dateTime('d/m/Y H:i')->sortable()->description(fn ($record) => $record->last_activity_at ? \Carbon\Carbon::parse($record->last_activity_at)->diffForHumans() : null),
                TextColumn::make('created_at')->label('Cadastro')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->crmFilters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('export_csv')->label('Exportar CSV completo')->icon('heroicon-o-arrow-down-tray')->color('success')->action(fn () => $this->exportCsv()),
            ])
            ->actions([
                Action::make('summary')->label('Resumo')->icon('heroicon-o-eye')->color('info')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalHeading(fn (User $record) => 'Resumo CRM: ' . ($record->name ?: $record->email))
                    ->modalContent(fn (User $record) => view('filament.pages.partials.crm-client-summary-modal', [
                        'record' => $record, 'profile' => $this->classifyClient($record), 'money' => fn ($v) => $this->money($v),
                    ])),
            ]);
    }

    public function crmFilters(): array
    {
        return [
            Filter::make('search_contact')->label('Buscar cliente')->form([Forms\Components\TextInput::make('search')->label('Nome, e-mail, CPF ou telefone')])
                ->query(function (Builder $q, array $data): Builder {
                    $search = trim((string) ($data['search'] ?? ''));
                    if ($search === '') return $q;
                    return $q->where(fn (Builder $w) => $w->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%")->orWhere('cpf','like',"%{$search}%")->orWhere('phone','like',"%{$search}%"));
                }),
            Filter::make('depositors')->label('Com depósito')->query(fn (Builder $q): Builder => $q->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) > 0")),
            Filter::make('never_deposited')->label('Nunca depositou')->query(fn (Builder $q): Builder => $q->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) = 0")),
            Filter::make('with_withdrawal')->label('Com saque')->query(fn (Builder $q): Builder => $q->whereRaw("(select count(*) from withdrawals where withdrawals.user_id = users.id) > 0")),
            Filter::make('influencers')->label('Influenciadores')->query(fn (Builder $q): Builder => $q->where('is_influencer', 1)),
            Filter::make('min_deposited')->label('Mínimo depositado')->form([Forms\Components\TextInput::make('amount')->label('Valor')->numeric()->prefix('R$')])
                ->query(fn (Builder $q, array $data): Builder => ((float)($data['amount'] ?? 0)) > 0 ? $q->whereRaw("(select coalesce(sum(amount),0) from deposits where deposits.user_id = users.id and deposits.status = 1) >= ?", [(float)$data['amount']]) : $q),
            Filter::make('min_bet')->label('Mínimo apostado')->form([Forms\Components\TextInput::make('amount')->label('Valor')->numeric()->prefix('R$')])
                ->query(fn (Builder $q, array $data): Builder => ((float)($data['amount'] ?? 0)) > 0 ? $q->whereRaw("(select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet') >= ?", [(float)$data['amount']]) : $q),
            Filter::make('created_at')->label('Cadastro')->form([Forms\Components\DatePicker::make('from')->label('De'), Forms\Components\DatePicker::make('until')->label('Até')])->columns(2)
                ->query(fn (Builder $q, array $data): Builder => $q->when($data['from'] ?? null, fn ($qq,$d) => $qq->whereDate('users.created_at','>=',$d))->when($data['until'] ?? null, fn ($qq,$d) => $qq->whereDate('users.created_at','<=',$d))),
        ];
    }

    public function crmQuery(): Builder
    {
        return User::query()
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('orders')->whereColumn('orders.user_id', 'users.id'))
            ->with('wallet')
            ->select('users.*')
            ->selectSub("select coalesce(sum(amount),0) from deposits where deposits.user_id = users.id and deposits.status = 1", 'total_deposited')
            ->selectSub("select coalesce(sum(amount),0) from withdrawals where withdrawals.user_id = users.id and withdrawals.status in (1,'paid','approved')", 'total_withdrawn')
            ->selectSub("select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet'", 'bet_volume')
            ->selectSub("select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'win'", 'win_volume')
            ->selectSub("greatest((select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet') - (select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'win'),0)", 'loss_volume')
            ->selectSub("(select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet') - (select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'win')", 'house_result')
            ->selectSub("select coalesce(balance,0)+coalesce(balance_bonus,0)+coalesce(balance_withdrawal,0) from wallets where wallets.user_id = users.id and wallets.active = 1 limit 1", 'wallet_total')
            ->selectSub("select count(distinct date(activity_date)) from (select orders.user_id, orders.created_at as activity_date from orders union all select transactions.user_id, transactions.created_at as activity_date from transactions union all select daily_bonus_claims.user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims) activity where activity.user_id = users.id and activity.activity_date >= date_sub(now(), interval 30 day)", 'activity_days')
            ->selectSub("select max(activity_date) from (select orders.user_id, orders.created_at as activity_date from orders union all select transactions.user_id, transactions.created_at as activity_date from transactions union all select daily_bonus_claims.user_id, daily_bonus_claims.claimed_at as activity_date from daily_bonus_claims) activity where activity.user_id = users.id", 'last_activity_at');
    }

    public function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['id','nome','email','telefone','cpf','total_apostado','total_depositado','total_ganho','total_perda','resultado_casa','total_sacado','saldo_atual','perfil','afiliado','influenciador','ultima_atividade','cadastro'], ';');
            $this->getFilteredTableQuery()->orderBy('users.id')->chunk(500, function ($records) use ($h) {
                foreach ($records as $record) {
                    fputcsv($h, [$record->id,$record->name,$record->email,$record->phone,$record->cpf,$this->fmt($record->bet_volume),$this->fmt($record->total_deposited),$this->fmt($record->win_volume),$this->fmt($record->loss_volume),$this->fmt($record->house_result),$this->fmt($record->total_withdrawn),$this->fmt($record->wallet_total),$this->classifyClient($record),$record->inviter ? (User::find($record->inviter)?->email ?: $record->inviter) : '', (int)($record->is_influencer ?? 0) === 1 ? 'sim':'nao', $record->last_activity_at, $record->created_at], ';');
                }
            });
            fclose($h);
        }, 'crm_clientes_completo_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function classifyClient(User $record): string
    {
        if ((bool)($record->is_influencer ?? false)) return 'Influenciador';
        $days = (int)($record->activity_days ?? 0);
        $dep = (float)($record->total_deposited ?? 0);
        $bet = (float)($record->bet_volume ?? 0);
        $wallet = (float)($record->wallet_total ?? 0);
        $last = $record->last_activity_at ? \Carbon\Carbon::parse($record->last_activity_at) : null;
        if ($dep >= 5000 || $bet >= 15000) return 'VIP';
        if ($bet >= 5000) return 'Apostador alto';
        if ($dep > 0 && $last && $last->lt(now()->subDays(15))) return 'Risco de abandono';
        if ($days >= 10 || $dep >= 500) return 'Recorrente';
        if ($dep > 0) return 'Depositante';
        if ($record->created_at && $record->created_at->gte(now()->subDays(7))) return 'Novo';
        if ($wallet > 0) return 'Saldo parado';
        return 'Inativo';
    }

    public function money($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
    private function fmt($v): string { return number_format((float)$v, 2, ',', '.'); }
}
