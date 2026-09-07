<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AdminManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-management-page';
    protected static ?string $title = 'Gerenciar Admins';
    protected static ?string $navigationLabel = 'Gerenciar Admins';
    protected static ?string $navigationGroup = 'Gestão de Administração';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'gerenciar-admins';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getAdminStats(): array
    {
        $admins = User::role('admin');

        return [
            'total' => (clone $admins)->count(),
            'active' => (clone $admins)->where('status', 'active')->count(),
            'inactive' => (clone $admins)->where('status', 'inactive')->count(),
            'created_today' => (clone $admins)->whereDate('created_at', now()->toDateString())->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(User::role('admin'))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Admin')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->copyable(),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cpf')->label('CPF')->searchable()->placeholder('-')->toggleable(),
                TextColumn::make('phone')->label('Telefone')->searchable()->placeholder('-')->toggleable(),
                IconColumn::make('active_status')
                    ->label('Ativo')
                    ->state(fn (User $record): bool => $this->isActive($record))
                    ->boolean(),
                TextColumn::make('admin_pin_status')
                    ->label('PIN admin')
                    ->state(fn (User $record) => filled($record->admin_action_pin) ? 'Configurado' : 'Pendente')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Configurado' ? 'success' : 'warning'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')->label('Somente ativos')->query(fn (Builder $q): Builder => $q->where('status', 'active')),
                Filter::make('inactive')->label('Somente inativos')->query(fn (Builder $q): Builder => $q->where('status', 'inactive')),
                Filter::make('created_at')
                    ->label('Cadastro')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn (Builder $x, $date) => $x->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $x, $date) => $x->whereDate('created_at', '<=', $date))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create_admin')
                    ->label('Cadastrar admin')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalHeading('Cadastrar novo administrador')
                    ->modalWidth('lg')
                    ->form($this->adminFormSchema(requirePassword: true))
                    ->action(function (array $data): void {
                        if (! $this->requireCurrentPin($data) || ! $this->validatePinFields($data, required: true)) return;

                        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null,
                            'password' => $data['password'],
                            'admin_action_pin' => Hash::make((string) $data['admin_action_pin']),
                            'status' => ! empty($data['status']) ? 'active' : 'inactive',
                        ]);
                        $user->assignRole($role);
                        AdminAudit::log('admin.create', $user, [], ['status' => $user->status, 'email' => $user->email], 'Criou administrador', $user->id);
                        Notification::make()->title('Admin cadastrado')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('edit_admin')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Editar admin: ' . ($record->name ?: $record->email))
                    ->modalWidth('lg')
                    ->form($this->editFormSchema())
                    ->fillForm(fn (User $record): array => [
                        'name' => $record->name,
                        'email' => $record->email,
                        'cpf' => $record->cpf,
                        'phone' => $record->phone,
                        'status' => $this->isActive($record),
                    ])
                    ->action(function (User $record, array $data): void {
                        if (! $this->requireCurrentPin($data)) return;
                        $before = $record->only(['name','email','cpf','phone','status']);
                        $payload = [
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null,
                            'status' => ! empty($data['status']) ? 'active' : 'inactive',
                        ];
                        if (! empty($data['password'])) $payload['password'] = $data['password'];
                        $record->update($payload);
                        if (! $record->hasRole('admin')) $record->assignRole('admin');
                        AdminAudit::log('admin.edit', $record, $before, $record->only(['name','email','cpf','phone','status']), 'Editou administrador', $record->id);
                        Notification::make()->title('Admin atualizado')->success()->send();
                    }),

                Action::make('reset_pin')
                    ->label('Redefinir PIN')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Redefinir PIN: ' . ($record->name ?: $record->email))
                    ->modalWidth('md')
                    ->form($this->pinFormSchema())
                    ->action(function (User $record, array $data): void {
                        if (! $this->requireCurrentPin($data) || ! $this->validatePinFields($data, required: true)) return;
                        $record->update(['admin_action_pin' => Hash::make((string) $data['admin_action_pin'])]);
                        AdminAudit::log('admin.pin.reset', $record, [], [], 'Redefiniu PIN administrativo', $record->id);
                        Notification::make()->title('PIN administrativo redefinido')->success()->send();
                    }),

                Action::make('toggle_status')
                    ->label(fn (User $record) => $this->isActive($record) ? 'Desativar' : 'Ativar')
                    ->icon(fn (User $record) => $this->isActive($record) ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $record) => $this->isActive($record) ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->form([$this->currentPinField()])
                    ->action(function (User $record, array $data): void {
                        if ($record->id === auth()->id()) {
                            Notification::make()->title('Ação bloqueada')->body('Você não pode alterar o próprio status administrativo.')->warning()->send();
                            return;
                        }
                        if (! $this->requireCurrentPin($data)) return;
                        $before = ['status' => $record->status];
                        $record->update(['status' => $this->isActive($record) ? 'inactive' : 'active']);
                        app(AdminActionGuard::class)->invalidateUserSessions($record);
                        AdminAudit::log('admin.status.toggle', $record, $before, ['status' => $record->status], 'Alterou status de administrador', $record->id);
                        Notification::make()->title('Status atualizado')->success()->send();
                    }),

                Action::make('remove_admin_role')
                    ->label('Remover admin')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([$this->currentPinField()])
                    ->action(function (User $record, array $data): void {
                        if ($record->id === auth()->id()) {
                            Notification::make()->title('Ação bloqueada')->body('Você não pode remover sua própria permissão de admin.')->warning()->send();
                            return;
                        }
                        if (! $this->requireCurrentPin($data)) return;
                        $record->removeRole('admin');
                        app(AdminActionGuard::class)->invalidateUserSessions($record);
                        AdminAudit::log('admin.role.remove', $record, ['admin' => true], ['admin' => false], 'Removeu permissão de administrador', $record->id);
                        Notification::make()->title('Permissão removida')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('disable_selected')
                    ->label('Desativar selecionados')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([$this->currentPinField()])
                    ->action(function (Collection $records, array $data): void {
                        if (! $this->requireCurrentPin($data)) return;
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->id === auth()->id()) continue;
                            $before = ['status' => $record->status];
                            $record->update(['status' => 'inactive']);
                            app(AdminActionGuard::class)->invalidateUserSessions($record);
                            AdminAudit::log('admin.status.disable', $record, $before, ['status' => 'inactive'], 'Desativou administrador em lote', $record->id);
                            $count++;
                        }
                        Notification::make()->title('Admins desativados')->body($count . ' admin(s) desativado(s).')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum admin encontrado');
    }

    private function adminFormSchema(bool $requirePassword): array
    {
        return [Forms\Components\Section::make('Dados do administrador')->schema([
            ...$this->baseAdminFields($requirePassword),
            ...$this->pinFields(),
            Forms\Components\Toggle::make('status')->label('Admin ativo')->default(true),
            $this->currentPinField(),
        ])->columns(2)];
    }

    private function editFormSchema(): array
    {
        return [Forms\Components\Section::make('Dados do administrador')->schema([
            ...$this->baseAdminFields(false),
            Forms\Components\Toggle::make('status')->label('Admin ativo')->default(true),
            $this->currentPinField(),
        ])->columns(2)];
    }

    private function pinFormSchema(): array
    {
        return [Forms\Components\Section::make('PIN administrativo')->schema([
            ...$this->pinFields(),
            $this->currentPinField(),
        ])->columns(2)];
    }

    private function baseAdminFields(bool $requirePassword): array
    {
        return [
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)->unique(table: User::class, column: 'email', ignoreRecord: true),
            Forms\Components\TextInput::make('cpf')->label('CPF')->maxLength(32),
            Forms\Components\TextInput::make('phone')->label('Telefone')->maxLength(32),
            Forms\Components\TextInput::make('password')->label($requirePassword ? 'Senha de login' : 'Nova senha de login')->password()->revealable()->required($requirePassword)->dehydrated(fn ($state) => filled($state))->rule(Password::min(8)),
        ];
    }

    private function pinFields(): array
    {
        return [
            Forms\Components\TextInput::make('admin_action_pin')->label('PIN administrativo')->password()->revealable()->numeric()->length(6)->required(),
            Forms\Components\TextInput::make('admin_action_pin_confirmation')->label('Confirmar PIN')->password()->revealable()->numeric()->length(6)->required()->same('admin_action_pin'),
        ];
    }

    private function validatePinFields(array $data, bool $required): bool
    {
        $pin = preg_replace('/\D/', '', (string) ($data['admin_action_pin'] ?? ''));
        $confirmation = preg_replace('/\D/', '', (string) ($data['admin_action_pin_confirmation'] ?? ''));
        if ($pin === '' && ! $required) return true;
        if (! preg_match('/^\d{6}$/', $pin) || $pin !== $confirmation) {
            Notification::make()->title('PIN administrativo inválido')->danger()->send();
            return false;
        }
        return true;
    }

    private function currentPinField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('current_admin_pin')
            ->label('Seu PIN administrativo atual')
            ->password()
            ->numeric()
            ->length(6)
            ->required(fn () => ! empty(auth()->user()?->admin_action_pin))
            ->helperText('Confirmação de segurança para ações administrativas sensíveis.');
    }

    private function requireCurrentPin(array $data): bool
    {
        $acting = auth()->user();
        if (empty($acting?->admin_action_pin)) return true;

        $guard = app(AdminActionGuard::class);
        if (! $guard->confirm((string) ($data['current_admin_pin'] ?? ''))) {
            $wait = $guard->availableIn();
            Notification::make()
                ->title('PIN incorreto')
                ->body($wait > 0 ? 'Muitas tentativas. Aguarde ' . $wait . ' segundo(s).' : 'Confirme o seu PIN administrativo atual.')
                ->danger()->send();
            return false;
        }
        return true;
    }

    private function isActive(User $record): bool
    {
        return in_array((string) $record->status, ['active', '1'], true);
    }
}
