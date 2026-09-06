<?php



namespace App\Filament\Pages;

use App\Models\Deposit;
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

class DepositHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.deposit-history-page';

    protected static ?string $title = 'Histórico de Depósitos';
    protected static ?string $navigationLabel = 'Histórico de Depósitos';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'historico-depositos';

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
        $query = Deposit::query();

        return [
            'total' => (clone $query)->count(),
            'paid' => (clone $query)->where('status', 1)->count(),
            'pending' => (clone $query)->where('status', 0)->count(),
            'amount' => (float) (clone $query)->where('status', 1)->sum('amount'),
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
                TextColumn::make('payment_id')->label('Pagamento')->searchable()->copyable()->limit(28)->tooltip(fn ($record) => $record->payment_id),
                TextColumn::make('amount')->label('Valor')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()->placeholder('-')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => (int) $state === 1 ? 'Pago' : ((int) $state === 2 ? 'Cancelado' : 'Pendente'))
                    ->color(fn ($state) => (int) $state === 1 ? 'success' : ((int) $state === 2 ? 'danger' : 'warning'))
                    ->sortable(),
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['0' => 'Pendente', '1' => 'Pago', '2' => 'Cancelado']),
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
        return Deposit::query()->with('user');
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
