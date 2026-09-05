<?php



namespace App\Filament\Pages;

use App\Models\UserVip;
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

class VipRedemptionHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.vip-redemption-history-page';

    protected static ?string $title = 'Histórico de Resgate VIP';
    protected static ?string $navigationLabel = 'Histórico VIP';
    protected static ?string $navigationGroup = 'Históricos';
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'historico-resgate-vip';

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
        $query = UserVip::query()
            ->whereNotNull('last_reward_claimed_at');

        $totalPaid = (float) (clone $query)
            ->join('vips', 'vips.id', '=', 'user_vips.vip_id')
            ->sum('vips.weekly_reward');

        return [
            'total' => (clone $query)->count(),
            'users' => (clone $query)->distinct('user_id')->count('user_id'),
            'total_paid' => $totalPaid,
            'latest' => optional((clone $query)->latest('last_reward_claimed_at')->value('last_reward_claimed_at'))->format('d/m/Y H:i') ?? '-',
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
                TextColumn::make('vip.title')->label('VIP')->searchable()->sortable()->badge()->placeholder('-'),
                TextColumn::make('vip.weekly_reward')->label('Valor pago')->formatStateUsing(fn ($state) => $this->money($state))->sortable()->placeholder('R$ 0,00'),
                TextColumn::make('last_reward_claimed_at')->label('Pago em')->dateTime('d/m/Y H:i')->sortable()->placeholder('-'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
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
        return UserVip::query()->with(['user', 'vip'])->whereNotNull('last_reward_claimed_at');
    }

    private function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}