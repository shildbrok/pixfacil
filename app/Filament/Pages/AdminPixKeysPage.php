<?php



namespace App\Filament\Pages;

use App\Models\AffiliateWithdraw;
use App\Models\UserPixKey;
use App\Models\Withdrawal;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminPixKeysPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-pix-keys-page';

    protected static ?string $title = 'Chaves PIX';
    protected static ?string $navigationLabel = 'Chaves PIX';
    protected static ?string $navigationGroup = 'Operação Financeira';
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'chaves-pix';

    public ?UserPixKey $selectedPixKey = null;
    public bool $showDetailsModal = false;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->query())
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (UserPixKey $record): string => $record->user?->email ?: 'Sem e-mail'),

                Tables\Columns\TextColumn::make('holder_name')
                    ->label('Titular')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (UserPixKey $record): string => 'CPF: ' . ($this->formatCpf($record->holder_cpf) ?: '—')),

                Tables\Columns\TextColumn::make('key_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $this->typeLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        UserPixKey::TYPE_DOCUMENT => 'success',
                        UserPixKey::TYPE_EMAIL => 'info',
                        UserPixKey::TYPE_PHONE => 'warning',
                        UserPixKey::TYPE_RANDOM => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pix_key')
                    ->label('Chave PIX')
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn (?string $state, UserPixKey $record): string => $this->formatPixKey($record))
                    ->tooltip(fn (UserPixKey $record): ?string => $record->pix_key),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Principal')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('withdrawals_count')
                    ->label('Saques')
                    ->state(fn (UserPixKey $record): int => $this->withdrawalsCount($record))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('affiliate_withdrawals_count')
                    ->label('Afiliado')
                    ->state(fn (UserPixKey $record): int => $this->affiliateWithdrawalsCount($record))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('key_type')
                    ->label('Tipo')
                    ->options($this->typeOptions()),

                Tables\Filters\SelectFilter::make('is_default')
                    ->label('Principal')
                    ->options([
                        '1' => 'Sim',
                        '0' => 'Não',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->booleanFilter($query, 'is_default', $data)),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (UserPixKey $record) => $this->openDetails($record)),

                Tables\Actions\Action::make('edit_pix_key')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (UserPixKey $record): string => 'Editar chave PIX #' . $record->id)
                    ->fillForm(fn (UserPixKey $record): array => [
                        'holder_name' => $record->holder_name,
                        'holder_cpf' => $record->holder_cpf,
                        'key_type' => $record->key_type,
                        'pix_key' => $record->pix_key,
                        'is_default' => $record->is_default ? '1' : '0',
                    ])
                    ->form($this->pixKeyFormSchema())
                    ->action(function (UserPixKey $record, array $data): void {
                        $payload = $this->normalizePayload($data);

                        $record->update($payload);

                        if ((bool) $payload['is_default']) {
                            UserPixKey::query()
                                ->where('user_id', $record->user_id)
                                ->whereKeyNot($record->id)
                                ->update(['is_default' => false]);
                        }

                        Notification::make()
                            ->title('Chave PIX atualizada')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('make_default')
                    ->label('Principal')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->visible(fn (UserPixKey $record): bool => ! (bool) $record->is_default)
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como chave principal?')
                    ->modalDescription('As outras chaves PIX deste cliente deixarão de ser principais.')
                    ->action(function (UserPixKey $record): void {
                        UserPixKey::query()
                            ->where('user_id', $record->user_id)
                            ->update(['is_default' => false]);

                        $record->update(['is_default' => true]);

                        Notification::make()
                            ->title('Chave marcada como principal')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->modalHeading('Excluir chave PIX?')
                    ->modalDescription('Essa ação remove apenas a chave cadastrada. Saques antigos continuam com os dados salvos no histórico.')
                    ->successNotificationTitle('Chave PIX excluída'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionadas')
                        ->modalHeading('Excluir chaves PIX selecionadas?')
                        ->modalDescription('Essa ação remove apenas as chaves cadastradas. Saques antigos continuam com os dados salvos no histórico.'),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function query(): Builder
    {
        return UserPixKey::query()->with('user');
    }

    private function pixKeyFormSchema(): array
    {
        return [
            Section::make('Dados da chave PIX')
                ->description('Edite apenas chaves que o cliente já cadastrou. Esta tela não permite criar novas chaves.')
                ->schema([
                    TextInput::make('holder_name')
                        ->label('Nome do titular')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('holder_cpf')
                        ->label('CPF do titular')
                        ->required()
                        ->maxLength(20)
                        ->helperText('Será salvo somente com números.'),

                    Select::make('key_type')
                        ->label('Tipo da chave')
                        ->options($this->typeOptions())
                        ->required()
                        ->native(false),

                    TextInput::make('pix_key')
                        ->label('Chave PIX')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Documento e telefone são salvos somente com números.'),

                    Select::make('is_default')
                        ->label('Chave principal')
                        ->options([
                            '1' => 'Sim',
                            '0' => 'Não',
                        ])
                        ->required()
                        ->native(false),
                ])
                ->columns(2),
        ];
    }

    private function normalizePayload(array $data): array
    {
        $type = (string) ($data['key_type'] ?? UserPixKey::TYPE_DOCUMENT);
        $pixKey = trim((string) ($data['pix_key'] ?? ''));

        if (in_array($type, [UserPixKey::TYPE_DOCUMENT, UserPixKey::TYPE_PHONE], true)) {
            $pixKey = preg_replace('/\D/', '', $pixKey);
        }

        return [
            'holder_name' => trim((string) ($data['holder_name'] ?? '')),
            'holder_cpf' => preg_replace('/\D/', '', (string) ($data['holder_cpf'] ?? '')),
            'key_type' => $type,
            'pix_key' => $pixKey,
            'is_default' => (string) ($data['is_default'] ?? '0') === '1',
        ];
    }

    private function booleanFilter(Builder $query, string $column, array $data): Builder
    {
        if (($data['value'] ?? null) !== null && ($data['value'] ?? '') !== '') {
            return $query->where($column, (int) $data['value']);
        }

        return $query;
    }

    public function openDetails(UserPixKey $record): void
    {
        $this->selectedPixKey = $record->load('user');
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->selectedPixKey = null;
        $this->showDetailsModal = false;
    }

    public function typeOptions(): array
    {
        return [
            UserPixKey::TYPE_DOCUMENT => 'CPF/CNPJ',
            UserPixKey::TYPE_EMAIL => 'E-mail',
            UserPixKey::TYPE_PHONE => 'Telefone',
            UserPixKey::TYPE_RANDOM => 'Chave aleatória',
        ];
    }

    public function typeLabel(?string $type): string
    {
        return $this->typeOptions()[$type] ?? ($type ?: '—');
    }

    public function formatCpf(?string $cpf): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf);

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        return $digits;
    }

    public function formatPixKey(UserPixKey $record): string
    {
        $value = (string) $record->pix_key;

        if ($record->key_type === UserPixKey::TYPE_DOCUMENT) {
            return $this->formatCpf($value);
        }

        if ($record->key_type === UserPixKey::TYPE_PHONE) {
            $digits = preg_replace('/\D/', '', $value);

            if (strlen($digits) === 11) {
                return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
            }

            return $digits;
        }

        return $value;
    }

    public function withdrawalsCount(UserPixKey $record): int
    {
        return (int) Withdrawal::query()
            ->where('user_id', $record->user_id)
            ->where('pix_key', $record->pix_key)
            ->count();
    }

    public function affiliateWithdrawalsCount(UserPixKey $record): int
    {
        return (int) AffiliateWithdraw::query()
            ->where('user_id', $record->user_id)
            ->where('pix_key', $record->pix_key)
            ->count();
    }

    public function stats(): array
    {
        return [
            'total' => UserPixKey::query()->count(),
            'default' => UserPixKey::query()->where('is_default', true)->count(),
            'document' => UserPixKey::query()->where('key_type', UserPixKey::TYPE_DOCUMENT)->count(),
            'email' => UserPixKey::query()->where('key_type', UserPixKey::TYPE_EMAIL)->count(),
            'phone' => UserPixKey::query()->where('key_type', UserPixKey::TYPE_PHONE)->count(),
            'random' => UserPixKey::query()->where('key_type', UserPixKey::TYPE_RANDOM)->count(),
            'users_with_keys' => UserPixKey::query()->distinct('user_id')->count('user_id'),
            'updated_today' => UserPixKey::query()->whereDate('updated_at', today())->count(),
            'last_update' => UserPixKey::query()->latest('updated_at')->value('updated_at'),
        ];
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}
