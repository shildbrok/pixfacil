<?php



namespace App\Filament\Pages;

use App\Models\Wallet;
use App\Support\AdminActionGuard;
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
use Illuminate\Support\Facades\DB;

class AdminWalletsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-wallets-page';

    protected static ?string $title = 'Carteiras';
    protected static ?string $navigationLabel = 'Carteiras';
    protected static ?string $navigationGroup = 'Operação Financeira';
    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'carteiras';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getWalletStats(): array
    {
        $base = Wallet::query();

        $totalWithdrawal = (float) (clone $base)->sum('balance_withdrawal');
        $totalDeposit = (float) (clone $base)->sum('balance');
        $totalBonus = (float) (clone $base)->sum('balance_bonus');
        $rolloverDeposit = (float) (clone $base)->sum('balance_deposit_rollover');
        $rolloverBonus = (float) (clone $base)->sum('balance_bonus_rollover');
        $affiliate = (float) (clone $base)->sum('refer_rewards');

        return [
            'wallets' => (clone $base)->count(),
            'active' => (clone $base)->where('active', 1)->count(),
            'total_available' => $totalWithdrawal + $totalDeposit + $totalBonus,
            'balance_withdrawal' => $totalWithdrawal,
            'balance' => $totalDeposit,
            'balance_bonus' => $totalBonus,
            'rollover_total' => $rolloverDeposit + $rolloverBonus,
            'rollover_deposit' => $rolloverDeposit,
            'rollover_bonus' => $rolloverBonus,
            'affiliate' => $affiliate,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->walletQuery())
            ->defaultSort('updated_at', 'desc')
            ->poll('30s')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('user_label')
                    ->label('Usuário')
                    ->state(fn (Wallet $record) => $record->user?->name ?: ($record->user?->email ?: 'Usuário #' . $record->user_id))
                    ->description(fn (Wallet $record) => $this->userDescription($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('users', 'users.id', '=', 'wallets.user_id')
                            ->orderBy('users.name', $direction)
                            ->select('wallets.*');
                    })
                    ->weight('bold'),

                TextColumn::make('total_available')
                    ->label('Total apostável')
                    ->state(fn (Wallet $record): float => $this->walletTotal($record))
                    ->badge()
                    ->color(fn (Wallet $record) => $this->walletTotal($record) > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $this->money($state)),

                TextColumn::make('balance_withdrawal')
                    ->label('Saque')
                    ->badge()
                    ->color(fn (Wallet $record) => (float) $record->balance_withdrawal > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('balance')
                    ->label('Depósito')
                    ->badge()
                    ->color(fn (Wallet $record) => (float) $record->balance > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('balance_bonus')
                    ->label('Bônus')
                    ->badge()
                    ->color(fn (Wallet $record) => (float) $record->balance_bonus > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('rollover_total')
                    ->label('Rollover')
                    ->state(fn (Wallet $record): float => $this->rolloverTotal($record))
                    ->badge()
                    ->color(fn (Wallet $record) => $this->rolloverTotal($record) > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $this->money($state)),

                TextColumn::make('refer_rewards')
                    ->label('Afiliado')
                    ->badge()
                    ->color(fn (Wallet $record) => (float) $record->refer_rewards > 0 ? 'primary' : 'gray')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('search_user')
                    ->label('Buscar usuário')
                    ->form([
                        Forms\Components\TextInput::make('search')
                            ->label('Nome, e-mail, CPF ou telefone'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['search'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->whereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    }),

                SelectFilter::make('active')
                    ->label('Status')
                    ->options([
                        1 => 'Ativas',
                        0 => 'Inativas',
                    ]),

                Filter::make('has_balance')
                    ->label('Com saldo apostável')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(balance_withdrawal + balance + balance_bonus) > 0'))
                    ->toggle(),

                Filter::make('has_withdrawal')
                    ->label('Com saque')
                    ->query(fn (Builder $query): Builder => $query->where('balance_withdrawal', '>', 0))
                    ->toggle(),

                Filter::make('has_deposit')
                    ->label('Com depósito')
                    ->query(fn (Builder $query): Builder => $query->where('balance', '>', 0))
                    ->toggle(),

                Filter::make('has_bonus')
                    ->label('Com bônus')
                    ->query(fn (Builder $query): Builder => $query->where('balance_bonus', '>', 0))
                    ->toggle(),

                Filter::make('has_rollover')
                    ->label('Com rollover')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $q) {
                        $q->where('balance_deposit_rollover', '>', 0)
                            ->orWhere('balance_bonus_rollover', '>', 0);
                    }))
                    ->toggle(),

                Filter::make('rollover_type')
                    ->label('Tipo de rollover')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->placeholder('Todos')
                            ->options([
                                'deposit' => 'Rollover de depósito',
                                'bonus' => 'Rollover de bônus',
                                'both' => 'Depósito e bônus',
                                'none' => 'Sem rollover',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['type'] ?? null) {
                            'deposit' => $query->where('balance_deposit_rollover', '>', 0),
                            'bonus' => $query->where('balance_bonus_rollover', '>', 0),
                            'both' => $query->where('balance_deposit_rollover', '>', 0)->where('balance_bonus_rollover', '>', 0),
                            'none' => $query->where('balance_deposit_rollover', '<=', 0)->where('balance_bonus_rollover', '<=', 0),
                            default => $query,
                        };
                    }),

                Filter::make('amount_range')
                    ->label('Faixa de valor')
                    ->form([
                        Forms\Components\Select::make('wallet_field')
                            ->label('Campo')
                            ->default('total')
                            ->options([
                                'total' => 'Total apostável',
                                'balance_withdrawal' => 'Carteira de saque',
                                'balance' => 'Carteira de depósito',
                                'balance_bonus' => 'Carteira de bônus',
                                'balance_deposit_rollover' => 'Rollover depósito',
                                'balance_bonus_rollover' => 'Rollover bônus',
                                'refer_rewards' => 'Comissão afiliado',
                            ]),

                        Forms\Components\TextInput::make('min')
                            ->label('Mínimo')
                            ->numeric()
                            ->prefix('R$'),

                        Forms\Components\TextInput::make('max')
                            ->label('Máximo')
                            ->numeric()
                            ->prefix('R$'),
                    ])
                    ->columns(3)
                    ->query(function (Builder $query, array $data): Builder {
                        $field = $data['wallet_field'] ?? 'total';

                        if ($field === 'total') {
                            return $query
                                ->when($data['min'] ?? null, fn (Builder $q, $value) => $q->whereRaw('(balance_withdrawal + balance + balance_bonus) >= ?', [(float) $value]))
                                ->when($data['max'] ?? null, fn (Builder $q, $value) => $q->whereRaw('(balance_withdrawal + balance + balance_bonus) <= ?', [(float) $value]));
                        }

                        $allowed = [
                            'balance_withdrawal',
                            'balance',
                            'balance_bonus',
                            'balance_deposit_rollover',
                            'balance_bonus_rollover',
                            'refer_rewards',
                        ];

                        if (! in_array($field, $allowed, true)) {
                            return $query;
                        }

                        return $query
                            ->when($data['min'] ?? null, fn (Builder $q, $value) => $q->where($field, '>=', (float) $value))
                            ->when($data['max'] ?? null, fn (Builder $q, $value) => $q->where($field, '<=', (float) $value));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->actions([
                Action::make('info')
                    ->label('Informações')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn (Wallet $record) => 'Informações: ' . ($record->user?->name ?: $record->user?->email ?: 'Usuário'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->modalContent(fn (Wallet $record) => view('filament.pages.partials.wallet-info-modal', [
                        'wallet' => $record,
                        'description' => $this->fullUserDescription($record),
                        'money' => fn ($value) => $this->money($value),
                        'walletTotal' => $this->walletTotal($record),
                        'rolloverTotal' => $this->rolloverTotal($record),
                    ])),

                Action::make('edit_wallet')
                    ->label('Editar carteira')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (Wallet $record) => 'Editar carteira de ' . ($record->user?->name ?: $record->user?->email ?: 'usuário'))
                    ->form([
                        ...$this->walletFormSchema(),
                        Forms\Components\TextInput::make('admin_pin')
                            ->label('PIN administrativo')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required()
                            ->helperText('Confirmação de segurança para alterar saldos.'),
                    ])
                    ->fillForm(fn (Wallet $record): array => [
                        'balance_withdrawal' => $record->balance_withdrawal,
                        'balance' => $record->balance,
                        'balance_bonus' => $record->balance_bonus,
                        'balance_deposit_rollover' => $record->balance_deposit_rollover,
                        'balance_bonus_rollover' => $record->balance_bonus_rollover,
                        'refer_rewards' => $record->refer_rewards,
                        'active' => (bool) $record->active,
                    ])
                    ->action(function (Wallet $record, array $data): void {
                        if (! app(AdminActionGuard::class)->confirm((string) ($data['admin_pin'] ?? ''))) {
                            Notification::make()
                                ->title('PIN incorreto')
                                ->body('O PIN administrativo não confere.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $auditFields = ['balance_withdrawal','balance','balance_bonus','balance_deposit_rollover','balance_bonus_rollover','refer_rewards','active'];
                        $auditBefore = $record->only($auditFields);

                        $record->update([
                            'balance_withdrawal' => (float) ($data['balance_withdrawal'] ?? 0),
                            'balance' => (float) ($data['balance'] ?? 0),
                            'balance_bonus' => (float) ($data['balance_bonus'] ?? 0),
                            'balance_deposit_rollover' => (float) ($data['balance_deposit_rollover'] ?? 0),
                            'balance_bonus_rollover' => (float) ($data['balance_bonus_rollover'] ?? 0),
                            'refer_rewards' => (float) ($data['refer_rewards'] ?? 0),
                            'active' => ! empty($data['active']) ? 1 : 0,
                        ]);

                        // C-02: registra a edicao manual de saldo na trilha de auditoria
                        \App\Support\AdminAudit::log(
                            'wallet.edit',
                            $record,
                            $auditBefore,
                            $record->fresh()->only($auditFields),
                            'Edicao manual de saldos/rollover da carteira',
                            $record->user_id
                        );

                        Notification::make()
                            ->title('Carteira atualizada')
                            ->body('Saldos e rollover foram salvos.')
                            ->success()
                            ->send();
                    }),

                Action::make('zero_rollover')
                    ->label('Zerar rollover')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Zerar rollover desta carteira?')
                    ->modalDescription('Isto vai zerar o rollover de depósito e bônus desta carteira. Os saldos não serão movidos automaticamente.')
                    ->form([
                        Forms\Components\TextInput::make('admin_pin')
                            ->label('PIN administrativo')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required(),
                    ])
                    ->action(function (Wallet $record, array $data): void {
                        if (! app(AdminActionGuard::class)->confirm((string) ($data['admin_pin'] ?? ''))) {
                            Notification::make()
                                ->title('PIN incorreto')
                                ->body('O PIN administrativo não confere.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'balance_deposit_rollover' => 0,
                            'balance_bonus_rollover' => 0,
                        ]);

                        Notification::make()
                            ->title('Rollover zerado')
                            ->success()
                            ->send();
                    }),

                Action::make('transfer_to_withdrawal')
                    ->label('Liberar saldo')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('info')
                    ->modalHeading('Transferir depósito e bônus para saque?')
                    ->modalDescription('Move os saldos de depósito e bônus para a carteira de saque e zera depósito/bônus. Use somente quando o rollover estiver correto.')
                    ->form([
                        Forms\Components\TextInput::make('admin_pin')
                            ->label('PIN administrativo')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required(),
                    ])
                    ->action(function (Wallet $record, array $data): void {
                        if (! app(AdminActionGuard::class)->confirm((string) ($data['admin_pin'] ?? ''))) {
                            Notification::make()
                                ->title('PIN incorreto')
                                ->body('O PIN administrativo não confere.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'balance_withdrawal' => (float) $record->balance_withdrawal + (float) $record->balance + (float) $record->balance_bonus,
                            'balance' => 0,
                            'balance_bonus' => 0,
                            'balance_deposit_rollover' => 0,
                            'balance_bonus_rollover' => 0,
                        ]);

                        Notification::make()
                            ->title('Saldo liberado')
                            ->body('Depósito e bônus foram movidos para saque.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhuma carteira encontrada')
            ->emptyStateDescription('Ajuste os filtros para localizar carteiras.');
    }

    private function walletQuery(): Builder
    {
        return Wallet::query()->with(['user.roles']);
    }

    private function walletFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Usuário')
                ->description('Dados apenas para conferência.')
                ->schema([
                    Forms\Components\Placeholder::make('user_name')
                        ->label('Nome')
                        ->content(fn (?Wallet $record): string => $record?->user?->name ?: '-'),

                    Forms\Components\Placeholder::make('user_email')
                        ->label('E-mail')
                        ->content(fn (?Wallet $record): string => $record?->user?->email ?: '-'),

                    Forms\Components\Placeholder::make('user_cpf')
                        ->label('CPF')
                        ->content(fn (?Wallet $record): string => $record?->user?->cpf ?: '-'),

                    Forms\Components\Placeholder::make('user_phone')
                        ->label('Telefone')
                        ->content(fn (?Wallet $record): string => $record?->user?->phone ?: '-'),
                ])
                ->columns(4),

            Forms\Components\Section::make('Saldos disponíveis')
                ->description('Ordem operacional de uso em apostas: saque, depósito e bônus.')
                ->schema([
                    Forms\Components\TextInput::make('balance_withdrawal')
                        ->label('Carteira de saque')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->required(),

                    Forms\Components\TextInput::make('balance')
                        ->label('Carteira de depósito')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->required(),

                    Forms\Components\TextInput::make('balance_bonus')
                        ->label('Carteira de bônus')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Rollover')
                ->description('Campos de rollover. Edite somente quando precisar corrigir saldo de rollover.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('balance_deposit_rollover')
                        ->label('Rollover de depósito')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->required(),

                    Forms\Components\TextInput::make('balance_bonus_rollover')
                        ->label('Rollover de bônus')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Afiliado e status')
                ->schema([
                    Forms\Components\TextInput::make('refer_rewards')
                        ->label('Comissão de afiliado')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0)
                        ->required(),

                    Forms\Components\Toggle::make('active')
                        ->label('Carteira ativa')
                        ->inline(false),
                ])
                ->columns(2),
        ];
    }

    private function userDescription(Wallet $wallet): ?string
    {
        return $wallet->user?->email;
    }

    private function fullUserDescription(Wallet $wallet): string
    {
        $user = $wallet->user;

        if (! $user) {
            return 'Usuário não encontrado.';
        }

        $parts = [
            'E-mail: ' . ($user->email ?: '-'),
            'CPF: ' . ($user->cpf ?: '-'),
            'Telefone: ' . ($user->phone ?: '-'),
            'Perfil: ' . ($user->hasRole('admin') ? 'Admin' : 'Usuário'),
            'Cadastro: ' . ($user->created_at?->format('d/m/Y H:i') ?: '-'),
            'Carteira atualizada: ' . ($wallet->updated_at?->format('d/m/Y H:i') ?: '-'),
        ];

        return implode("\n", $parts);
    }

    private function walletTotal(Wallet $wallet): float
    {
        return (float) $wallet->balance_withdrawal
            + (float) $wallet->balance
            + (float) $wallet->balance_bonus;
    }

    private function rolloverTotal(Wallet $wallet): float
    {
        return (float) $wallet->balance_deposit_rollover
            + (float) $wallet->balance_bonus_rollover;
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}