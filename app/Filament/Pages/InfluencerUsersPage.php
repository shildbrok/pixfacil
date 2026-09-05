<?php



namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AdminActionGuard;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InfluencerUsersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.influencer-users-page';

    protected static ?string $title = 'Usuários Influenciadores';
    protected static ?string $navigationLabel = 'Influenciadores';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'influenciadores';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(User::query()->with('wallet')->where('is_influencer', 1))
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->columns([
                TextColumn::make('name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email),

                TextColumn::make('wallet.balance')
                    ->label('Depósito')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('wallet.balance_bonus')
                    ->label('Bônus')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('wallet.balance_withdrawal')
                    ->label('Saque')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('wallet.total_balance')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),

                IconColumn::make('is_influencer')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('add_influencer')
                    ->label('Adicionar influenciador')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->form([
                        Select::make('user_id')
                            ->label('Usuário')
                            ->searchable()
                            ->required()
                            ->placeholder('Digite nome, e-mail ou CPF')
                            ->getSearchResultsUsing(function (string $search): array {
                                return User::query()
                                    ->where('is_influencer', 0)
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%")
                                            ->orWhere('cpf', 'like', "%{$search}%");
                                    })
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn (User $user) => [
                                        $user->id => trim(($user->name ?: 'Usuário') . ' — ' . $user->email . ' — CPF: ' . ($user->cpf ?: '-')),
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $user = User::find($value);

                                if (! $user) {
                                    return null;
                                }

                                return trim(($user->name ?: 'Usuário') . ' — ' . $user->email . ' — CPF: ' . ($user->cpf ?: '-'));
                            })
                            ->helperText('Pesquise e selecione um usuário existente. Apenas usuários ainda não marcados como influenciador aparecem na busca.'),
                    ])
                    ->action(function (array $data): void {
                        $user = User::query()->find($data['user_id'] ?? null);

                        if (! $user) {
                            Notification::make()
                                ->title('Usuário não encontrado')
                                ->body('Selecione um usuário válido na lista.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $user->update(['is_influencer' => 1]);

                        Notification::make()
                            ->title('Influenciador ativado')
                            ->body('O usuário agora está em modo influenciador.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('adjust_wallet')
                    ->label('Ajustar saldo')
                    ->icon('heroicon-o-wallet')
                    ->color('warning')
                    ->slideOver()
                    ->form([
                        TextInput::make('balance')
                            ->label('Carteira de depósito')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('balance_bonus')
                            ->label('Carteira de bônus')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('balance_withdrawal')
                            ->label('Carteira de saque')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('admin_pin')
                            ->label('PIN administrativo')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required()
                            ->helperText('Confirmação de segurança para ajustar saldos.'),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'balance' => (float) ($record->wallet?->balance ?? 0),
                        'balance_bonus' => (float) ($record->wallet?->balance_bonus ?? 0),
                        'balance_withdrawal' => (float) ($record->wallet?->balance_withdrawal ?? 0),
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

                        DB::transaction(function () use ($record, $data): void {
                            $wallet = $record->wallet()->lockForUpdate()->firstOrFail();
                            $wallet->balance = round((float) $data['balance'], 2);
                            $wallet->balance_bonus = round((float) $data['balance_bonus'], 2);
                            $wallet->balance_withdrawal = round((float) $data['balance_withdrawal'], 2);
                            $wallet->save();
                        });

                        Notification::make()
                            ->title('Saldo ajustado')
                            ->success()
                            ->send();
                    }),

                Action::make('disable_influencer')
                    ->label('Desativar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Desativar modo influenciador?')
                    ->modalDescription('O usuário voltará ao fluxo financeiro normal.')
                    ->action(function (User $record): void {
                        $record->update(['is_influencer' => 0]);

                        Notification::make()
                            ->title('Modo influenciador desativado')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum influenciador ativo')
            ->emptyStateDescription('Use o botão “Adicionar influenciador” para pesquisar e selecionar um usuário existente.');
    }
}