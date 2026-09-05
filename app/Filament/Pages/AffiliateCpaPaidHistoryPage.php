<?php



namespace App\Filament\Pages;

use App\Models\AffiliateHistory;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AffiliateCpaPaidHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.affiliate-cpa-paid-history-page';

    protected static ?string $title = 'Histórico de CPA Pago';
    protected static ?string $navigationLabel = 'Histórico de CPA Pago';
    protected static ?string $navigationGroup = 'Afiliados';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'historico-cpa-pago';

    public ?int $affiliate = null;

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

    public function mount(): void
    {
        $affiliate = request()->query('affiliate');
        $this->affiliate = is_numeric($affiliate) ? (int) $affiliate : null;
    }

    public function getCpaStats(): array
    {
        $query = $this->baseQuery();

        $totalPaid = (float) (clone $query)->sum('commission_paid');
        $count = (clone $query)->count();
        $affiliates = (clone $query)->distinct('inviter')->count('inviter');
        $indicated = (clone $query)->distinct('user_id')->count('user_id');

        return [
            'count' => $count,
            'affiliates' => $affiliates,
            'indicated' => $indicated,
            'total_paid' => $totalPaid,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->baseQuery()->with('user'))
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('affiliate_name')
                    ->label('Afiliado')
                    ->state(fn (AffiliateHistory $record) => $this->affiliateName($record->inviter))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereIn('inviter', User::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->pluck('id'));
                    })
                    ->weight('bold')
                    ->description(fn (AffiliateHistory $record) => $this->affiliateEmail($record->inviter)),

                TextColumn::make('user.name')
                    ->label('Indicado')
                    ->searchable()
                    ->description(fn (AffiliateHistory $record) => $record->user?->email)
                    ->placeholder('-'),

                TextColumn::make('deposited_amount')
                    ->label('Total depositado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('commission')
                    ->label('CPA configurado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('commission_paid')
                    ->label('CPA pago')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('updated_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->updated_at?->diffForHumans()),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('affiliate')
                    ->label('Afiliado')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Nome ou e-mail do afiliado')
                            ->placeholder('Digite nome ou e-mail'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['search'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        $ids = User::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->pluck('id');

                        return $query->whereIn('inviter', $ids);
                    }),

                Filter::make('indicated')
                    ->label('Indicado')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Nome ou e-mail do indicado')
                            ->placeholder('Digite nome ou e-mail'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['search'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->whereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    }),

                Filter::make('paid_at')
                    ->label('Período de pagamento')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('updated_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('updated_at', '<=', $date));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->emptyStateHeading('Nenhum CPA pago encontrado')
            ->emptyStateDescription('Quando um CPA for pago para afiliado, ele aparecerá neste histórico.');
    }

    private function baseQuery(): Builder
    {
        return AffiliateHistory::query()
            ->where('commission_type', 'cpa')
            ->where('status', 1)
            ->when($this->affiliate, fn (Builder $query) => $query->where('inviter', $this->affiliate));
    }

    private function affiliateName($id): string
    {
        $user = User::find($id);

        return $user?->name ?: 'Afiliado #' . $id;
    }

    private function affiliateEmail($id): ?string
    {
        return User::find($id)?->email;
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
