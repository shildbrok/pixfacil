<?php



namespace App\Filament\Pages;

use App\Models\LogsRoundsFree;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LogsRoundsFreePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.logs-rounds-free-page';

    protected static ?string $title = 'Histórico de Rodadas Grátis';
    protected static ?string $navigationLabel = 'Histórico de Freebets';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'historico-freebets';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }


    public function getHistoryStats(): array
    {
        $latest = LogsRoundsFree::query()
            ->latest('created_at')
            ->value('created_at');

        return [
            'total' => LogsRoundsFree::query()->count(),
            'success' => LogsRoundsFree::query()->where('status', 1)->count(),
            'failed' => LogsRoundsFree::query()->where('status', 0)->count(),
            'today' => LogsRoundsFree::query()->whereDate('created_at', today())->count(),
            'latest' => $latest ? \Carbon\Carbon::parse($latest)->format('d/m/Y H:i') : '—',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(LogsRoundsFree::query())
            ->poll('30s')
            ->striped()
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('username')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Usuário copiado com sucesso!')
                    ->description(fn ($record) => $record->user_email ? '📧 ' . $record->user_email : null),

                TextColumn::make('game_code')
                    ->label('Jogo')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => (int) $state === 1 ? 'Sucesso' : 'Falhou')
                    ->colors([
                        'success' => fn ($state) => (int) $state === 1,
                        'danger' => fn ($state) => (int) $state === 0,
                    ]),

                TextColumn::make('message')
                    ->label('Mensagem')
                    ->wrap()
                    ->limit(70)
                    ->tooltip(fn ($record) => $record->message ?: null),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at?->diffForHumans()),
            ])
            ->filters([
                Filter::make('periodo')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De'),

                        Forms\Components\DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)
                            );
                    }),

                Filter::make('status')
                    ->label('Status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Filtrar por status')
                            ->options([
                                '' => 'Todos',
                                '1' => 'Sucesso',
                                '0' => 'Falhou',
                            ])
                            ->default(''),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['status'] ?? '') !== '') {
                            $query->where('status', (int) $data['status']);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading('Excluir registro do histórico?')
                    ->modalDescription('Essa ação remove definitivamente este registro de rodadas grátis.')
                    ->successNotificationTitle('Registro excluído com sucesso.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Excluir selecionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->modalHeading('Excluir registros selecionados?')
                        ->modalDescription('Essa ação remove definitivamente todos os históricos selecionados.')
                        ->successNotificationTitle('Registros selecionados excluídos com sucesso.'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhum registro encontrado')
            ->emptyStateDescription('Quando houver envio ou processamento de rodadas grátis, os registros aparecerão aqui.');
    }
}