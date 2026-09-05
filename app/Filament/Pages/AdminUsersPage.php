<?php



namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AdminActionGuard;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

use Illuminate\Support\Collection;
class AdminUsersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-users-page';

    protected static ?string $title = 'Usuários';
    protected static ?string $navigationLabel = 'Usuários';
    protected static ?string $navigationGroup = 'Operação de Jogadores';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'usuarios';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getUserStats(): array
    {
        $total = User::query()->count();

        $depositors = DB::table('deposits')
            ->where('status', 1)
            ->distinct('user_id')
            ->count('user_id');

        $bettors = DB::table('orders')
            ->where('type', 'bet')
            ->distinct('user_id')
            ->count('user_id');

        $walletTotal = (float) DB::table('wallets')
            ->where('active', 1)
            ->sum(DB::raw('coalesce(balance,0) + coalesce(balance_bonus,0) + coalesce(balance_withdrawal,0)'));

        $totalDeposited = (float) DB::table('deposits')
            ->where('status', 1)
            ->sum('amount');

        return [
            'total' => $total,
            'depositors' => $depositors,
            'bettors' => $bettors,
            'influencers' => User::query()->where('is_influencer', 1)->count(),
            'wallet_total' => $walletTotal,
            'total_deposited' => $totalDeposited,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->usersQuery())
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->copyable(),

                TextColumn::make('wallet_total')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('total_deposited')
                    ->label('Depositado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('total_bet')
                    ->label('Apostado')
                    ->formatStateUsing(fn ($state) => $this->money($state))
                    ->sortable(),

                TextColumn::make('profile')
                    ->label('Perfil')
                    ->badge()
                    ->state(fn (User $record) => $this->profileLabel($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Influencer' => 'purple',
                        'VIP' => 'warning',
                        'Apostador alto' => 'danger',
                        'Depositante' => 'success',
                        'Afiliado' => 'info',
                        'Novo' => 'gray',
                        default => 'gray',
                    }),

                IconColumn::make('is_influencer')
                    ->label('Influencer')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')
                    ->label('Buscar')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Nome, e-mail, CPF ou telefone'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    }),

                Filter::make('depositors')
                    ->label('Com depósito')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) > 0")),

                Filter::make('never_deposited')
                    ->label('Nunca depositou')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) = 0")),

                Filter::make('bettors')
                    ->label('Com apostas')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("(select count(*) from orders where orders.user_id = users.id and orders.type = 'bet') > 0")),

                Filter::make('is_influencer')
                    ->label('Influenciadores')
                    ->query(fn (Builder $query): Builder => $query->where('is_influencer', 1)),

                Filter::make('affiliates')
                    ->label('Afiliados')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('inviter_code')->where('inviter_code', '!=', '')),

                Filter::make('created_at')
                    ->label('Cadastro')
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
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('create_user')
                    ->label('Novo usuário')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalHeading('Cadastrar usuário')
                    ->modalWidth('lg')
                    ->form($this->createFormSchema())
                    ->action(function (array $data): void {
                        User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null,
                            'password' => $data['password'],
                            'status' => ! empty($data['status']) ? 'active' : 'inactive',
                            'is_influencer' => ! empty($data['is_influencer']) ? 1 : 0,
                            'inviter_code' => $data['inviter_code'] ?? null,
                            'affiliate_cpa' => $data['affiliate_cpa'] ?? 0,
                            'affiliate_baseline' => $data['affiliate_baseline'] ?? 0,
                        ]);

                        Notification::make()
                            ->title('Usuário cadastrado')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('info')
                    ->label('Informações')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->url(fn (User $record) => AdminUserInformationPage::getUrl(['record' => $record->id])),

                Action::make('edit_user')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Editar usuário: ' . ($record->name ?: $record->email))
                    ->modalWidth('lg')
                    ->form($this->editFormSchema())
                    ->fillForm(fn (User $record): array => [
                        'name' => $record->name,
                        'email' => $record->email,
                        'cpf' => $record->cpf,
                        'phone' => $record->phone,
                        'status' => (string) $record->status === 'active' || (string) $record->status === '1',
                        'is_influencer' => (bool) $record->is_influencer,
                        'inviter_code' => $record->inviter_code,
                        'affiliate_cpa' => $record->affiliate_cpa,
                        'affiliate_baseline' => $record->affiliate_baseline,
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null,
                            'status' => ! empty($data['status']) ? 'active' : 'inactive',
                            'is_influencer' => ! empty($data['is_influencer']) ? 1 : 0,
                            'inviter_code' => $data['inviter_code'] ?? null,
                            'affiliate_cpa' => $data['affiliate_cpa'] ?? 0,
                            'affiliate_baseline' => $data['affiliate_baseline'] ?? 0,
                        ]);

                        Notification::make()
                            ->title('Usuário atualizado')
                            ->success()
                            ->send();
                    }),

                Action::make('reset_password')
                    ->label('Senha')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Alterar senha: ' . ($record->email))
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Nova senha')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::min(8)),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar nova senha')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(),

                        Forms\Components\TextInput::make('admin_pin')
                            ->label('PIN administrativo')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        if (! app(AdminActionGuard::class)->confirm((string) ($data['admin_pin'] ?? ''))) {
                            Notification::make()
                                ->title('PIN incorreto')
                                ->body('O PIN administrativo não confere.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'password' => $data['password'],
                        ]);

                        app(AdminActionGuard::class)->invalidateUserSessions($record);

                        Notification::make()
                            ->title('Senha alterada')
                            ->body('O usuário precisará fazer login novamente.')
                            ->success()
                            ->send();
                    }),

                Action::make('delete_user')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => 'Excluir usuário: ' . ($record->name ?: $record->email))
                    ->modalDescription('Essa ação remove o usuário e todos os registros relacionados: carteira, apostas, depósitos, saques, resgates, sessões, chaves PIX e históricos.')
                    ->action(function (User $record): void {
                        $this->deleteUsersCascade(collect([$record]));

                        Notification::make()
                            ->title('Usuário excluído')
                            ->body('O usuário e os registros relacionados foram removidos.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete_selected_users')
                        ->label('Excluir selecionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Excluir usuários selecionados?')
                        ->modalDescription('Essa ação remove os usuários selecionados e todos os registros relacionados: carteiras, apostas, depósitos, saques, resgates, sessões, chaves PIX e históricos.')
                        ->action(function (Collection $records): void {
                            $count = $records->count();

                            $this->deleteUsersCascade($records);

                            Notification::make()
                                ->title('Usuários excluídos')
                                ->body($count . ' usuário(s) e seus registros relacionados foram removidos.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Nenhum usuário encontrado')
            ->emptyStateDescription('Ajuste os filtros para encontrar usuários.');
    }


    private function deleteUsersCascade(Collection $users): void
    {
        $ids = $users
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $authId = (int) auth()->id();

        if ($ids->contains($authId)) {
            throw new \RuntimeException('Você não pode excluir o próprio usuário logado.');
        }

        DB::transaction(function () use ($ids): void {
            $idList = $ids->all();

            $this->deleteFromTablesByColumn($idList, [
                'wallets',
                'deposits',
                'withdrawals',
                'affiliate_withdraws',
                'orders',
                'user_pix_keys',
                'game_sessions',
                'mission_user',
                'mission_completions',
                'vip_user',
                'vip_redemptions',
                'daily_bonus_histories',
                'daily_bonus_user',
                'rounds_free_user',
                'logs_rounds_free',
                'kyc_verifications',
                'kyc_documents',
                'password_reset_tokens',
                'model_has_roles',
                'model_has_permissions',
                'personal_access_tokens',
                'notifications',
            ], 'user_id');

            $this->deleteFromTablesByColumn($idList, [
                'sessions',
            ], 'user_id');

            $this->deleteFromTablesByColumn($idList, [
                'affiliate_histories',
            ], 'user_id');


            $this->deleteFromTablesByColumn($idList, [
                'affiliate_histories',
            ], 'inviter');


            $this->nullFromTablesByColumn($idList, [
                'users',
            ], 'inviter');

            User::query()
                ->whereIn('id', $idList)
                ->delete();
        });
    }

    private function deleteFromTablesByColumn(array $ids, array $tables, string $column): void
    {
        foreach ($tables as $table) {
            if (! $this->tableHasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereIn($column, $ids)
                ->delete();
        }
    }

    private function nullFromTablesByColumn(array $ids, array $tables, string $column): void
    {
        foreach ($tables as $table) {
            if (! $this->tableHasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereIn($column, $ids)
                ->update([$column => null]);
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function usersQuery(): Builder
    {
        return User::query()
            ->with('wallet')
            ->select('users.*')
            ->selectSub("select coalesce(sum(amount),0) from deposits where deposits.user_id = users.id and deposits.status = 1", 'total_deposited')
            ->selectSub("select coalesce(sum(amount),0) from orders where orders.user_id = users.id and orders.type = 'bet'", 'total_bet')
            ->selectSub("select coalesce(balance,0)+coalesce(balance_bonus,0)+coalesce(balance_withdrawal,0) from wallets where wallets.user_id = users.id and wallets.active = 1 limit 1", 'wallet_total');
    }

    private function createFormSchema(): array
    {
        return array_merge($this->baseFormSchema(), [
            Forms\Components\TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                ->required()
                ->rule(Password::min(8)),
        ]);
    }

    private function editFormSchema(): array
    {
        return $this->baseFormSchema();
    }

    private function baseFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Dados do usuário')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->required()
                        ->maxLength(191)
                        ->unique(table: User::class, column: 'email', ignoreRecord: true),

                    Forms\Components\TextInput::make('cpf')
                        ->label('CPF')
                        ->maxLength(32),

                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone')
                        ->maxLength(32),
                ])
                ->columns(2),

            Forms\Components\Section::make('Configurações')
                ->schema([
                    Forms\Components\Toggle::make('status')
                        ->label('Conta ativa')
                        ->default(true),

                    Forms\Components\Toggle::make('is_influencer')
                        ->label('Influenciador')
                        ->default(false),

                    Forms\Components\TextInput::make('inviter_code')
                        ->label('Código de afiliado')
                        ->maxLength(191),

                    Forms\Components\TextInput::make('affiliate_cpa')
                        ->label('CPA personalizado')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('affiliate_baseline')
                        ->label('Depósito mínimo CPA')
                        ->numeric()
                        ->prefix('R$')
                        ->default(0),
                ])
                ->columns(2),
        ];
    }

    public function profileLabel(User $record): string
    {
        if ((bool) ($record->is_influencer ?? false)) {
            return 'Influencer';
        }

        $deposited = (float) ($record->total_deposited ?? 0);
        $bet = (float) ($record->total_bet ?? 0);

        if ($deposited >= 5000 || $bet >= 15000) {
            return 'VIP';
        }

        if ($bet >= 5000) {
            return 'Apostador alto';
        }

        if ($deposited > 0) {
            return 'Depositante';
        }

        if (filled($record->inviter_code)) {
            return 'Afiliado';
        }

        if ($record->created_at && $record->created_at->gte(now()->subDays(7))) {
            return 'Novo';
        }

        return 'Comum';
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}