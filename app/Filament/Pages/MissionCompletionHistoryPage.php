<?php



namespace App\Filament\Pages;

use App\Models\MissionUser;
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

class MissionCompletionHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.mission-completion-history-page';

    protected static ?string $title = 'Histórico de Missões Completas';
    protected static ?string $navigationLabel = 'Histórico de Missões';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'historico-missoes-completas';

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
        $query = MissionUser::query();

        return [
            'total' => (clone $query)->count(),
            'redeemed' => (clone $query)->where('redeemed', 1)->count(),
            'pending' => (clone $query)->where('redeemed', 0)->count(),
            'rewards' => (float) (clone $query)->where('redeemed', 1)->sum('reward'),
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
                TextColumn::make('mission.title')->label('Missão')->searchable()->sortable()->wrap()->placeholder('-'),
                TextColumn::make('reward')->label('Recompensa')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('current_progress')->label('Progresso')->sortable()->toggleable(),
                IconColumn::make('redeemed')->label('Resgatada')->boolean()->sortable(),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Atualizado em')->dateTime('d/m/Y H:i')->sortable()->toggleable(),
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
                SelectFilter::make('redeemed')
                    ->label('Status')
                    ->options(['0' => 'Pendente', '1' => 'Resgatada']),
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
        return MissionUser::query()->with(['user', 'mission']);
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
