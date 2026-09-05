<?php



namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AdminActionGuard;
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
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getAdminStats(): array
    {
        $admins = User::role('admin');

        return [
            'total' => (clone $admins)->count(),
            'active' => (clone $admins)->where('status', 1)->count(),
            'inactive' => (clone $admins)->where('status', 0)->count(),
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
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Admin')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->copyable()
                    ->copyMessage('Admin copiado.'),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('E-mail copiado.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('admin_pin_status')
                    ->label('PIN admin')
                    ->state(fn (User $record) => filled($record->admin_action_pin) ? 'Configurado' : 'Pendente')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Configurado' ? 'success' : 'warning'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Somente ativos')
                    ->query(fn (Builder $query): Builder => $query->where('status', 1)),

                Filter::make('inactive')
                    ->label('Somente inativos')
                    ->query(fn (Builder $query): Builder => $query->where('status', 0)),

                Filter::make('created_at')
                    ->label('Cadastro')
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
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create_admin')
                    ->label('Cadastrar admin')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalHeading('Cadastrar novo administrador')
                    ->modalDescription('Crie um usuário administrador com acesso ao painel.')
                    ->modalWidth('lg')
                    ->form($this->adminFormSchema(requirePassword: true))
                    ->action(function (array $data): void {
                        if (! $this->requireCurrentPin($data)) {
                            return;
                        }

                        if (! $this->validatePinFields($data, required: true)) {
                            return;
                        }

                        $adminRole = Role::firstOrCreate([
                            'name' => 'admin',
                            'guard_name' => 'web',
                        ]);

                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null,
                            'password' => $data['password'],
                            'admin_action_pin' => Hash::make((string) $data['admin_action_pin']),
                            'status' => ! empty($data['status']) ? 1 : 0,
                        ]);

                        $user->assignRole($adminRole);

                        Notification::make()
                            ->title('Admin cadastrado')
                            ->body('O novo administrador foi criado com sucesso.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('edit_admin')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Editar admin: ' . ($record->name ?: $record->email))
                    ->modalDescription('Altere dados do admin e senha de login. O PIN administrativo fica em “Redefinir PIN”.')
                    ->modalWidth('lg')
                    ->form($this->editFormSchema())
                    ->fillForm(fn (User $record): array => [
                        'name' => $record->name,
                        'email' => $record->email,
                        'cpf' => $record->cpf,
                        'phone' => $record->phone,
                        'status' => (bool) $record->status,
                    ])
                    ->action(function (User $record, array $data): void {
                        if (! $this->requireCurrentPin($data)) {
                            return;
                        }

                        $payload = [
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'cpf' => $data['cpf'] ?? null,
                            'phone' => $data['phone'] ?? null,
                            'status' => ! empty($data['status']) ? 1 : 0,
                        ];

                        if (! empty($data['password'])) {
                            $payload['password'] = $data['password'];
                        }

                        $record->update($payload);

                        if (! $record->hasRole('admin')) {
                            $record->assignRole('admin');
                        }

                        Notification::make()
                            ->title('Admin atualizado')
                            ->success()
                            ->send();
                    }),

                Action::make('reset_pin')
                    ->label('Redefinir PIN')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->slideOver()
                    ->modalHeading(fn (User $record) => 'Redefinir PIN: ' . ($record->name ?: $record->email))
                    ->modalDescription('Crie um novo PIN administrativo de 6 dígitos para confirmar ações sensíveis.')
                    ->modalWidth('md')
                    ->form($this->pinFormSchema())
                    ->action(function (User $record, array $data): void {
                        if (! $this->requireCurrentPin($data)) {
                            return;
                        }

                        if (! $this->validatePinFields($data, required: true)) {
                            return;
                        }

                        $record->update([
                            'admin_action_pin' => Hash::make((string) $data['admin_action_pin']),
                        ]);

                        Notification::make()
                            ->title('PIN administrativo redefinido')
                            ->body('O novo PIN foi salvo com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('toggle_status')
                    ->label(fn (User $record) => $record->status ? 'Desativar' : 'Ativar')
                    ->icon(fn (User $record) => $record->status ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $record) => $record->status ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => $record->status ? 'Desativar admin?' : 'Ativar admin?')
                    ->modalDescription('Essa ação altera o acesso deste administrador.')
                    ->action(function (User $record): void {
                        if ($record->id === auth()->id()) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body('Você não pode desativar o próprio usuário administrador.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => $record->status ? 0 : 1,
                        ]);

                        Notification::make()
                            ->title('Status atualizado')
                            ->success()
                            ->send();
                    }),

                Action::make('remove_admin_role')
                    ->label('Remover admin')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remover permissão de admin?')
                    ->modalDescription('O usuário continuará cadastrado, mas perderá acesso administrativo.')
                    ->action(function (User $record): void {
                        if ($record->id === auth()->id()) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body('Você não pode remover sua própria permissão de admin.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->removeRole('admin');

                        Notification::make()
                            ->title('Permissão removida')
                            ->body('O usuário não é mais administrador.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('disable_selected')
                    ->label('Desativar selecionados')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $count = 0;

                        foreach ($records as $record) {
                            if ($record->id === auth()->id()) {
                                continue;
                            }

                            $record->update(['status' => 0]);
                            $count++;
                        }

                        Notification::make()
                            ->title('Admins desativados')
                            ->body($count . ' admin(s) desativado(s).')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum admin encontrado')
            ->emptyStateDescription('Cadastre administradores para acesso ao painel.');
    }

    private function adminFormSchema(bool $requirePassword): array
    {
        return [
            Forms\Components\Section::make('Dados do administrador')
                ->schema([
                    ...$this->baseAdminFields($requirePassword),
                    ...$this->pinFields(),
                    Forms\Components\Toggle::make('status')
                        ->label('Admin ativo')
                        ->default(true),
                    $this->currentPinField(),
                ])
                ->columns(2),
        ];
    }

    private function editFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Dados do administrador')
                ->description('Este formulário não altera o PIN administrativo.')
                ->schema([
                    ...$this->baseAdminFields(requirePassword: false),
                    Forms\Components\Toggle::make('status')
                        ->label('Admin ativo')
                        ->default(true),
                    $this->currentPinField(),
                ])
                ->columns(2),
        ];
    }

    private function pinFormSchema(): array
    {
        return [
            Forms\Components\Section::make('PIN administrativo')
                ->description('O PIN deve ter exatamente 6 números.')
                ->schema([
                    ...$this->pinFields(),
                    $this->currentPinField(),
                ])
                ->columns(2),
        ];
    }

    private function baseAdminFields(bool $requirePassword): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(table: User::class, column: 'email', ignoreRecord: true),

            Forms\Components\TextInput::make('cpf')
                ->label('CPF')
                ->maxLength(32),

            Forms\Components\TextInput::make('phone')
                ->label('Telefone')
                ->maxLength(32),

            Forms\Components\TextInput::make('password')
                ->label($requirePassword ? 'Senha de login' : 'Nova senha de login')
                ->password()
                ->revealable()
                ->required($requirePassword)
                ->dehydrated(fn ($state) => filled($state))
                ->rule(Password::min(8))
                ->helperText($requirePassword ? 'Mínimo de 8 caracteres.' : 'Preencha somente se quiser alterar a senha de login.'),
        ];
    }

    private function pinFields(): array
    {
        return [
            Forms\Components\TextInput::make('admin_action_pin')
                ->label('PIN administrativo')
                ->password()
                ->revealable()
                ->numeric()
                ->length(6)
                ->required()
                ->helperText('Use exatamente 6 números. Ex.: 918023'),

            Forms\Components\TextInput::make('admin_action_pin_confirmation')
                ->label('Confirmar PIN')
                ->password()
                ->revealable()
                ->numeric()
                ->length(6)
                ->required()
                ->same('admin_action_pin'),
        ];
    }

    private function validatePinFields(array $data, bool $required): bool
    {
        $pin = preg_replace('/\D/', '', (string) ($data['admin_action_pin'] ?? ''));
        $confirmation = preg_replace('/\D/', '', (string) ($data['admin_action_pin_confirmation'] ?? ''));

        if ($pin === '' && ! $required) {
            return true;
        }

        if (! preg_match('/^\d{6}$/', $pin) || $pin !== $confirmation) {
            Notification::make()
                ->title('PIN administrativo inválido')
                ->body('Informe e confirme um PIN numérico de exatamente 6 dígitos.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    /**
     * Campo de confirmação: exige o PIN atual DO ADMIN LOGADO antes de
     * criar admin, trocar senha de admin ou redefinir PIN. Sem isso, uma
     * sessão de admin sequestrada podia auto-reemitir o segundo fator.
     */
    private function currentPinField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('current_admin_pin')
            ->label('Seu PIN administrativo atual')
            ->password()
            ->revealable()
            ->numeric()
            ->length(6)
            ->required(fn () => ! empty(auth()->user()?->admin_action_pin))
            ->helperText('Confirmação de segurança: informe o SEU PIN atual para autorizar esta ação.');
    }

    private function requireCurrentPin(array $data): bool
    {
        $actingUser = auth()->user();

        // Bootstrap: se o admin logado ainda não tem PIN, permite (não há o que confirmar).
        if (empty($actingUser?->admin_action_pin)) {
            return true;
        }

        if (! app(AdminActionGuard::class)->confirm((string) ($data['current_admin_pin'] ?? ''))) {
            Notification::make()
                ->title('PIN incorreto')
                ->body('Confirme o SEU PIN administrativo atual para executar esta ação.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }
}