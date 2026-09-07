<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

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
        return static::canAccess();
    }

    public function getUserStats(): array
    {
        $walletTotal = (float) DB::table('wallets')
            ->where('active', 1)
            ->sum(DB::raw('coalesce(balance,0) + coalesce(balance_bonus,0) + coalesce(balance_withdrawal,0)'));

        return [
            'total' => User::query()->count(),
            'depositors' => DB::table('deposits')->where('status', 1)->distinct('user_id')->count('user_id'),
            'bettors' => DB::table('orders')->where('type', 'bet')->distinct('user_id')->count('user_id'),
            'influencers' => User::query()->where('is_influencer', 1)->count(),
            'wallet_total' => $walletTotal,
            'total_deposited' => (float) DB::table('deposits')->where('status', 1)->sum('amount'),
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
                TextColumn::make('wallet_total')->label('Saldo')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('total_deposited')->label('Depositado')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('total_bet')->label('Apostado')->formatStateUsing(fn ($state) => $this->money($state))->sortable(),
                TextColumn::make('profile')
                    ->label('Perfil')
                    ->badge()
                    ->state(fn (User $record) => $this->profileLabel($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Influencer' => 'purple', 'VIP' => 'warning', 'Apostador alto' => 'danger',
                        'Depositante' => 'success', 'Afiliado' => 'info', 'Novo' => 'gray', default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Conta')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $this->isActiveState($state) ? 'Ativa' : 'Arquivada')
                    ->color(fn ($state) => $this->isActiveState($state) ? 'success' : 'gray'),
                IconColumn::make('is_influencer')->label('Influencer')->boolean()->sortable(),
                TextColumn::make('created_at')->label('Cadastro')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')
                    ->label('Buscar')
                    ->form([Forms\Components\TextInput::make('value')->label('Nome, e-mail, CPF ou telefone')])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));
                        if ($search === '') return $query;
                        return $query->where(fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('cpf', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                    }),
                Filter::make('active')->label('Contas ativas')->query(fn (Builder $q): Builder => $q->where('status', 'active')),
                Filter::make('archived')->label('Contas arquivadas')->query(fn (Builder $q): Builder => $q->where('status', 'inactive')),
                Filter::make('depositors')->label('Com depósito')->query(fn (Builder $q): Builder => $q->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) > 0")),
                Filter::make('never_deposited')->label('Nunca depositou')->query(fn (Builder $q): Builder => $q->whereRaw("(select count(*) from deposits where deposits.user_id = users.id and deposits.status = 1) = 0")),
                Filter::make('bettors')->label('Com apostas')->query(fn (Builder $q): Builder => $q->whereRaw("(select count(*) from orders where orders.user_id = users.id and orders.type = 'bet') > 0")),
                Filter::make('is_influencer')->label('Influenciadores')->query(fn (Builder $q): Builder => $q->where('is_influencer', 1)),
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
                        $user = User::create([
                            'name' => $data['name'], 'email' => $data['email'], 'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null, 'password' => $data['password'],
                            'status' => ! empty($data['status']) ? 'active' : 'inactive',
                            'is_influencer' => ! empty($data['is_influencer']) ? 1 : 0,
                            'inviter_code' => $data['inviter_code'] ?? null,
                            'affiliate_cpa' => $data['affiliate_cpa'] ?? 0,
                            'affiliate_baseline' => $data['affiliate_baseline'] ?? 0,
                        ]);
                        AdminAudit::log('user.create', $user, [], ['status' => $user->status, 'email' => $user->email], 'Criou usuário', $user->id);
                        Notification::make()->title('Usuário cadastrado')->success()->send();
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
                        'name' => $record->name, 'email' => $record->email, 'cpf' => $record->cpf,
                        'phone' => $record->phone, 'status' => $this->isActiveState($record->status),
                        'is_influencer' => (bool) $record->is_influencer, 'inviter_code' => $record->inviter_code,
                        'affiliate_cpa' => $record->affiliate_cpa, 'affiliate_baseline' => $record->affiliate_baseline,
                    ])
                    ->action(function (User $record, array $data): void {
                        $before = $record->only(['name','email','cpf','phone','status','is_influencer','inviter_code','affiliate_cpa','affiliate_baseline']);
                        $record->update([
                            'name' => $data['name'], 'email' => $data['email'], 'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null, 'status' => ! empty($data['status']) ? 'active' : 'inactive',
                            'is_influencer' => ! empty($data['is_influencer']) ? 1 : 0,
                            'inviter_code' => $data['inviter_code'] ?? null,
                            'affiliate_cpa' => $data['affiliate_cpa'] ?? 0,
                            'affiliate_baseline' => $data['affiliate_baseline'] ?? 0,
                        ]);
                        AdminAudit::log('user.edit', $record, $before, $record->only(array_keys($before)), 'Editou usuário', $record->id);
                        Notification::make()->title('Usuário atualizado')->success()->send();
                    }),

                Action::make('reset_password')
                    ->label('Senha')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\TextInput::make('password')->label('Nova senha')->password()->revealable()->required()->rule(Password::min(8)),
                        Forms\Components\TextInput::make('password_confirmation')->label('Confirmar nova senha')->password()->revealable()->same('password')->required(),
                        $this->adminPinField(),
                    ])
                    ->action(function (User $record, array $data): void {
                        if (! $this->confirmPin($data)) return;
                        $record->update(['password' => $data['password']]);
                        app(AdminActionGuard::class)->invalidateUserSessions($record);
                        AdminAudit::log('user.password.reset', $record, [], [], 'Redefiniu senha do usuário', $record->id);
                        Notification::make()->title('Senha alterada')->body('O usuário precisará fazer login novamente.')->success()->send();
                    }),

                Action::make('archive_user')
                    ->label(fn (User $record) => $this->isActiveState($record->status) ? 'Arquivar' : 'Reativar')
                    ->icon(fn (User $record) => $this->isActiveState($record->status) ? 'heroicon-o-archive-box' : 'heroicon-o-arrow-path')
                    ->color(fn (User $record) => $this->isActiveState($record->status) ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => $this->isActiveState($record->status) ? 'Arquivar usuário?' : 'Reativar usuário?')
                    ->modalDescription('O histórico financeiro, apostas, depósitos, saques e auditorias serão preservados.')
                    ->form([$this->adminPinField()])
                    ->action(function (User $record, array $data): void {
                        if (! $this->confirmPin($data)) return;
                        $before = ['status' => $record->status, 'banned' => $record->banned];
                        $activate = ! $this->isActiveState($record->status);
                        $record->update(['status' => $activate ? 'active' : 'inactive']);
                        if (! $activate) app(AdminActionGuard::class)->invalidateUserSessions($record);
                        AdminAudit::log($activate ? 'user.reactivate' : 'user.archive', $record, $before, ['status' => $record->status, 'banned' => $record->banned], $activate ? 'Reativou usuário' : 'Arquivou usuário preservando histórico', $record->id);
                        Notification::make()->title($activate ? 'Usuário reativado' : 'Usuário arquivado')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhum usuário encontrado')
            ->emptyStateDescription('Ajuste os filtros para encontrar usuários.');
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
        return [...$this->baseFormSchema(), Forms\Components\TextInput::make('password')->label('Senha')->password()->revealable()->required()->rule(Password::min(8))];
    }

    private function editFormSchema(): array
    {
        return $this->baseFormSchema();
    }

    private function baseFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Dados do usuário')->schema([
                Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(191),
                Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->maxLength(191)->unique(table: User::class, column: 'email', ignoreRecord: true),
                Forms\Components\TextInput::make('cpf')->label('CPF')->maxLength(32),
                Forms\Components\TextInput::make('phone')->label('Telefone')->maxLength(32),
            ])->columns(2),
            Forms\Components\Section::make('Configurações')->schema([
                Forms\Components\Toggle::make('status')->label('Conta ativa')->default(true),
                Forms\Components\Toggle::make('is_influencer')->label('Influenciador')->default(false),
                Forms\Components\TextInput::make('inviter_code')->label('Código de afiliado')->maxLength(191),
                Forms\Components\TextInput::make('affiliate_cpa')->label('CPA personalizado')->numeric()->default(0),
                Forms\Components\TextInput::make('affiliate_baseline')->label('Depósito mínimo CPA')->numeric()->prefix('R$')->default(0),
            ])->columns(2),
        ];
    }

    private function adminPinField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('admin_pin')->label('PIN administrativo')->password()->numeric()->length(6)->required();
    }

    private function confirmPin(array $data): bool
    {
        $guard = app(AdminActionGuard::class);
        if ($guard->confirm((string) ($data['admin_pin'] ?? ''))) return true;
        $wait = $guard->availableIn();
        Notification::make()->title('PIN incorreto')->body($wait > 0 ? 'Muitas tentativas. Aguarde ' . $wait . ' segundo(s).' : 'O PIN administrativo não confere.')->danger()->send();
        return false;
    }

    private function isActiveState(mixed $state): bool
    {
        return in_array((string) $state, ['active', '1'], true);
    }

    public function profileLabel(User $record): string
    {
        if ((bool) ($record->is_influencer ?? false)) return 'Influencer';
        $deposited = (float) ($record->total_deposited ?? 0);
        $bet = (float) ($record->total_bet ?? 0);
        if ($deposited >= 5000 || $bet >= 15000) return 'VIP';
        if ($bet >= 5000) return 'Apostador alto';
        if ($deposited > 0) return 'Depositante';
        if (filled($record->inviter_code)) return 'Afiliado';
        if ($record->created_at && $record->created_at->gte(now()->subDays(7))) return 'Novo';
        return 'Comum';
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
