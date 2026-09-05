<?php



namespace App\Filament\Pages;

use App\Models\AggregatorWallet;
use App\Services\PlayFiverBalanceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminAggregatorWalletsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-aggregator-wallets-page';

    protected static ?string $title = 'Carteiras PlayFiver';
    protected static ?string $navigationLabel = 'Carteiras PlayFiver';
    protected static ?string $navigationGroup = 'Operação Financeira';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'carteiras-playfiver';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function syncBalances(): void
    {
        try {
            $result = app(PlayFiverBalanceService::class)->syncBalances();
            $status = $result['blocked'] ?? ['blocked' => false];

            Notification::make()
                ->title('Saldos sincronizados')
                ->body(($status['blocked'] ?? false)
                    ? ($status['message'] ?? 'Bloqueio operacional ativo.')
                    : 'Carteiras PlayFiver atualizadas com sucesso.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Falha ao sincronizar PlayFiver')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getStats(): array
    {
        $query = AggregatorWallet::query()->where('provider', 'playfiver');

        $total = (float) (clone $query)->sum('balance');
        $operational = (float) (clone $query)->where('info_only', 0)->sum('balance');
        $informative = (float) (clone $query)->where('info_only', 1)->sum('balance');
        $primary = (clone $query)->where('is_primary', 1)->first();

        $belowNotify = (clone $query)
            ->where('notify_enabled', 1)
            ->whereNotNull('notify_threshold')
            ->whereColumn('balance', '<', 'notify_threshold')
            ->count();

        $withError = (clone $query)
            ->whereNotNull('last_error')
            ->where('last_error', '!=', '')
            ->count();

        return [
            'count' => (clone $query)->count(),
            'total' => $total,
            'operational' => $operational,
            'informative' => $informative,
            'primary_name' => $primary?->name ?: '-',
            'primary_balance' => (float) ($primary?->balance ?? 0),
            'primary_blocking' => $primary?->isBlockingNow() ?? false,
            'below_notify' => $belowNotify,
            'with_error' => $withError,
            'last_sync' => (clone $query)->max('last_synced_at'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->walletQuery())
            ->poll('30s')
            ->defaultSort('is_primary', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('name')
                    ->label('Carteira')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (AggregatorWallet $record) => $record->wallet_key),

                TextColumn::make('type_label')
                    ->label('Tipo')
                    ->badge()
                    ->state(fn (AggregatorWallet $record): string => $this->walletTypeLabel($record))
                    ->color(fn (AggregatorWallet $record): string => match ($this->walletTypeLabel($record)) {
                        'Principal' => 'success',
                        'Informativa' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('balance')
                    ->label('Saldo')
                    ->badge()
                    ->money('BRL')
                    ->sortable()
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : 'gray')
                    ->description(fn (AggregatorWallet $record) => $record->isBlockingNow()
                        ? 'Abaixo do limite operacional fixo'
                        : null),

                TextColumn::make('notify_threshold')
                    ->label('Alerta abaixo')
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : $this->money($state))
                    ->badge()
                    ->color(fn (AggregatorWallet $record) => $record->isBelowNotificationThreshold() ? 'danger' : 'gray'),

                IconColumn::make('notify_enabled')
                    ->label('Alerta')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('notify_email')
                    ->label('E-mail')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_synced_at')
                    ->label('Sincronização')
                    ->since()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('last_error')
                    ->label('Erro')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')
                    ->label('Buscar')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Nome ou chave da carteira'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('wallet_key', 'like', "%{$search}%");
                        });
                    }),

                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options([
                        'primary' => 'Principal',
                        'operational' => 'Operacional',
                        'info_only' => 'Informativa',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'primary' => $query->where('is_primary', 1),
                            'operational' => $query->where('info_only', 0),
                            'info_only' => $query->where('info_only', 1),
                            default => $query,
                        };
                    }),

                Filter::make('below_notify')
                    ->label('Abaixo do alerta')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('notify_enabled', 1)
                        ->whereNotNull('notify_threshold')
                        ->whereColumn('balance', '<', 'notify_threshold'))
                    ->toggle(),

                Filter::make('with_error')
                    ->label('Com erro')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('last_error')
                        ->where('last_error', '!=', ''))
                    ->toggle(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('sync')
                    ->label('Sincronizar saldo agora')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(fn () => $this->syncBalances()),
            ])
            ->actions([
                Action::make('info')
                    ->label('Informações')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn (AggregatorWallet $record) => 'Carteira: ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->modalContent(fn (AggregatorWallet $record) => view('filament.pages.partials.aggregator-wallet-info-modal', [
                        'wallet' => $record,
                        'money' => fn ($value) => $this->money($value),
                        'typeLabel' => $this->walletTypeLabel($record),
                    ])),

                Action::make('notification_settings')
                    ->label('Sistema de notificação')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->slideOver()
                    ->modalWidth('lg')
                    ->modalHeading(fn (AggregatorWallet $record) => 'Notificação: ' . $record->name)
                    ->form($this->notificationFormSchema())
                    ->fillForm(fn (AggregatorWallet $record): array => [
                        'notify_enabled' => (bool) $record->notify_enabled,
                        'notify_email' => $record->notify_email,
                        'notify_threshold' => $record->notify_threshold,
                    ])
                    ->action(function (AggregatorWallet $record, array $data): void {
                        $record->update([
                            'notify_enabled' => ! empty($data['notify_enabled']),
                            'notify_email' => $data['notify_email'] ?? null,
                            'notify_threshold' => $data['notify_threshold'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Notificação atualizada')
                            ->body('As regras de alerta desta carteira foram salvas.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhuma carteira PlayFiver encontrada')
            ->emptyStateDescription('Clique em Sincronizar saldo agora para buscar as carteiras diretamente da PlayFiver.');
    }

    private function walletQuery(): Builder
    {
        return AggregatorWallet::query()
            ->where('provider', 'playfiver');
    }

    private function notificationFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Sistema de notificação')
                ->description('Configure somente alertas. O saldo, carteira principal e tipo da carteira vêm da sincronização da PlayFiver.')
                ->schema([
                    Forms\Components\Toggle::make('notify_enabled')
                        ->label('Ativar alerta por e-mail')
                        ->inline(false),

                    Forms\Components\TextInput::make('notify_email')
                        ->label('E-mail para alerta')
                        ->email()
                        ->maxLength(191)
                        ->helperText('Recebe aviso quando o saldo ficar abaixo do limite configurado.'),

                    Forms\Components\TextInput::make('notify_threshold')
                        ->label('Alertar abaixo de')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->helperText('Exemplo: 100.00'),
                ]),
        ];
    }

    public function walletTypeLabel(AggregatorWallet $wallet): string
    {
        if ($wallet->is_primary) {
            return 'Principal';
        }

        if ($wallet->info_only) {
            return 'Informativa';
        }

        return 'Operacional';
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}