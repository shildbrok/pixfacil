<?php



namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

class AffiliateManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.affiliate-management-page';

    protected static ?string $title = 'Gestão de Afiliados';
    protected static ?string $navigationLabel = 'Afiliados';
    protected static ?string $navigationGroup = 'Afiliados';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'gestao-afiliados';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getAffiliateStats(): array
    {
        $affiliatesQuery = User::query()
            ->where(function (Builder $query) {
                $query->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('users as referrals')
                        ->whereColumn('referrals.inviter', 'users.id');
                })
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('affiliate_histories')
                        ->whereColumn('affiliate_histories.inviter', 'users.id');
                });
            });

        $affiliateIds = (clone $affiliatesQuery)->pluck('id');

        $totalReferrals = User::query()
            ->whereIn('inviter', $affiliateIds)
            ->count();

        $depositors = DB::table('deposits')
            ->join('users as referrals', 'referrals.id', '=', 'deposits.user_id')
            ->whereIn('referrals.inviter', $affiliateIds)
            ->where('deposits.status', 1)
            ->distinct('deposits.user_id')
            ->count('deposits.user_id');

        $totalDeposited = (float) DB::table('deposits')
            ->join('users as referrals', 'referrals.id', '=', 'deposits.user_id')
            ->whereIn('referrals.inviter', $affiliateIds)
            ->where('deposits.status', 1)
            ->sum('deposits.amount');

        $totalCpaPaid = (float) DB::table('affiliate_histories')
            ->whereIn('inviter', $affiliateIds)
            ->where('commission_type', 'cpa')
            ->where('status', 1)
            ->sum('commission_paid');

        return [
            'affiliates' => (clone $affiliatesQuery)->count(),
            'referrals' => $totalReferrals,
            'depositors' => $depositors,
            'total_deposited' => $totalDeposited,
            'total_cpa_paid' => $totalCpaPaid,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->affiliateQuery())
            ->defaultSort('total_deposited', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Afiliado')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->copyable()
                    ->copyMessage('Afiliado copiado.'),

                TextColumn::make('inviter_code')
                    ->label('Código')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Código copiado.')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('affiliate_cpa')
                    ->label('CPA %')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.') . '%')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('affiliate_baseline')
                    ->label('Dep. mínimo CPA')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_cpa_paid')
                    ->label('Total recebido')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->description('CPA pago'),

                TextColumn::make('total_referrals')
                    ->label('Total indicados')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('depositing_referrals')
                    ->label('Indicados depositantes')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('total_deposited')
                    ->label('Total de lucro')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->description('Soma dos depósitos pagos dos indicados'),

                TextColumn::make('wallet.refer_rewards')
                    ->label('Saldo afiliado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('only_with_depositors')
                    ->label('Somente com depositantes')
                    ->query(function (Builder $query): Builder {
                        return $query->whereRaw(
                            "(select count(distinct deposits.user_id)
                              from deposits
                              inner join users as referrals on referrals.id = deposits.user_id
                              where referrals.inviter = users.id and deposits.status = 1) > 0"
                        );
                    }),

                Filter::make('only_with_cpa_paid')
                    ->label('Somente com CPA pago')
                    ->query(function (Builder $query): Builder {
                        return $query->whereRaw(
                            "(select coalesce(sum(affiliate_histories.commission_paid), 0)
                              from affiliate_histories
                              where affiliate_histories.inviter = users.id
                                and affiliate_histories.status = 1
                                and affiliate_histories.commission_type = 'cpa') > 0"
                        );
                    }),

                Filter::make('created_at')
                    ->label('Cadastro do afiliado')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('users.created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('users.created_at', '<=', $date));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->actions([
                Action::make('configure_cpa')
                    ->label('Configurar')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('warning')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Configurar CPA: ' . ($record->name ?: $record->email))
                    ->modalDescription('Defina a porcentagem de CPA e o depósito mínimo para o afiliado receber comissão dos indicados.')
                    ->modalWidth('lg')
                    ->form([
                        Section::make('Regra de CPA do afiliado')
                            ->description('Esses valores substituem a configuração global apenas para este afiliado.')
                            ->schema([
                                TextInput::make('affiliate_cpa')
                                    ->label('Porcentagem do CPA (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step('0.01')
                                    ->suffix('%')
                                    ->required()
                                    ->helperText('Percentual que o afiliado recebe sobre o valor depositado pelo indicado. Ex.: 10 = 10%.'),

                                TextInput::make('affiliate_baseline')
                                    ->label('Depósito mínimo do indicado (R$)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->prefix('R$')
                                    ->required()
                                    ->helperText('Valor mínimo que o indicado precisa depositar para liberar CPA ao afiliado.'),
                            ])
                            ->columns(2),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'affiliate_cpa' => (float) ($record->affiliate_cpa ?? 0),
                        'affiliate_baseline' => (float) ($record->affiliate_baseline ?? 0),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'affiliate_cpa' => round((float) ($data['affiliate_cpa'] ?? 0), 2),
                            'affiliate_baseline' => round((float) ($data['affiliate_baseline'] ?? 0), 2),
                        ]);

                        Notification::make()
                            ->title('CPA do afiliado atualizado')
                            ->body('As regras de CPA deste afiliado foram salvas com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('view_cpa_history')
                    ->label('CPA pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->url(fn (User $record) => AffiliateCpaPaidHistoryPage::getUrl([
                        'affiliate' => $record->id,
                    ])),
            ])
            ->emptyStateHeading('Nenhum afiliado encontrado')
            ->emptyStateDescription('Afiliados aparecem aqui quando possuem indicados, histórico de CPA ou comissão.');
    }

    private function affiliateQuery(): Builder
    {
        return User::query()
            ->with('wallet')
            ->select('users.*')
            ->selectSub(
                "select count(*) from users as referrals where referrals.inviter = users.id",
                'total_referrals'
            )
            ->selectSub(
                "select count(distinct deposits.user_id)
                 from deposits
                 inner join users as referrals on referrals.id = deposits.user_id
                 where referrals.inviter = users.id and deposits.status = 1",
                'depositing_referrals'
            )
            ->selectSub(
                "select coalesce(sum(deposits.amount), 0)
                 from deposits
                 inner join users as referrals on referrals.id = deposits.user_id
                 where referrals.inviter = users.id and deposits.status = 1",
                'total_deposited'
            )
            ->selectSub(
                "select coalesce(sum(affiliate_histories.commission_paid), 0)
                 from affiliate_histories
                 where affiliate_histories.inviter = users.id
                   and affiliate_histories.status = 1
                   and affiliate_histories.commission_type = 'cpa'",
                'total_cpa_paid'
            )
            ->where(function (Builder $query) {
                $query->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('users as referrals')
                        ->whereColumn('referrals.inviter', 'users.id');
                })
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('affiliate_histories')
                        ->whereColumn('affiliate_histories.inviter', 'users.id');
                })
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('wallets')
                        ->whereColumn('wallets.user_id', 'users.id')
                        ->where('wallets.refer_rewards', '>', 0);
                });
            });
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}