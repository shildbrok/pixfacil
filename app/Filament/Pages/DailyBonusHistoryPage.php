<?php



namespace App\Filament\Pages;

use App\Models\DailyBonusClaim;
use App\Models\DailyBonusConfig;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DailyBonusHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.daily-bonus-history-page';

    protected static ?string $title = 'Histórico Bônus Diário';
    protected static ?string $navigationLabel = 'Histórico Bônus Diário';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'historico-bonus-diario';

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
        $query = DailyBonusClaim::query();
        $bonusValue = $this->currentBonusValue();

        return [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->whereDate('claimed_at', now()->toDateString())->count(),
            'users' => (clone $query)->distinct('user_id')->count('user_id'),
            'bonus_value' => $bonusValue,
            'estimated_total' => (float) (clone $query)->count() * $bonusValue,
            'latest' => optional((clone $query)->latest('claimed_at')->value('claimed_at'))->format('d/m/Y H:i') ?? '-',
        ];
    }


    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->historyQuery())
            ->defaultSort('claimed_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Usuário')->searchable()->sortable()->weight('bold')
                    ->description(fn ($record) => $record->user?->email),
                TextColumn::make('bonus_value')
                    ->label('Valor do bônus')
                    ->state(fn () => $this->currentBonusValue())
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->badge()
                    ->color('success')
                    ->description('Valor atual configurado'),
                TextColumn::make('claimed_at')->label('Resgatado em')->dateTime('d/m/Y H:i')->sortable()
                    ->description(fn ($record) => $record->claimed_at ? \Carbon\Carbon::parse($record->claimed_at)->diffForHumans() : null),
            ])
            ->filters([
                Filter::make('user')
                    ->label('Usuário')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Nome, e-mail ou CPF'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['search'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->whereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%");
                        });
                    }),
                Filter::make('claimed_at')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('claimed_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('claimed_at', '<=', $date));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('clear_all')
                    ->label('Limpar histórico')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Limpar este histórico?')
                    ->modalDescription('Essa ação remove todos os registros deste histórico. Use somente quando tiver certeza.')
                    ->action(function (): void {
                        $deleted = $this->historyQuery()->delete();

                        Notification::make()
                            ->title('Histórico limpo')
                            ->body($deleted . ' registro(s) removido(s).')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkAction::make('delete_selected')
                    ->label('Excluir selecionados')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $count = $records->count();
                        $records->each->delete();

                        Notification::make()
                            ->title('Registros excluídos')
                            ->body($count . ' registro(s) removido(s).')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum registro encontrado')
            ->emptyStateDescription('Este histórico ainda não possui registros para os filtros atuais.');
    }

    private function historyQuery(): Builder
    {
        return DailyBonusClaim::query()->with('user');
    }

    private function currentBonusValue(): float
    {
        return (float) (DailyBonusConfig::query()->latest('id')->value('bonus_value') ?? 0);
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}