<?php



namespace App\Filament\Pages;

use App\Helpers\Core;
use App\Http\Controllers\Api\Profile\WalletController;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Support\AdminActionGuard;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AdminWithdrawalsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-withdrawals-page';

    protected static ?string $title = 'Saques de Usuários';
    protected static ?string $navigationLabel = 'Saques de Usuários';
    protected static ?string $navigationGroup = 'Saques';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'saques-de-usuarios';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Withdrawal::query()->where('status', 0)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $pending = Withdrawal::query()->where('status', 0)->count();

        return $pending >= 10 ? 'danger' : ($pending >= 3 ? 'warning' : 'success');
    }

    public function stats(): array
    {
        return cache()->remember('admin:withdrawals:stats:v1', 30, function (): array {
        $base = Withdrawal::query();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 0)->count(),
            'processing' => (clone $base)->where('status', 9)->count(),
            'approved' => (clone $base)->where('status', 1)->count(),
            'cancelled' => (clone $base)->where('status', 2)->count(),
            'pending_amount' => (float) (clone $base)->where('status', 0)->sum('amount'),
            'today_amount' => (float) (clone $base)->whereDate('created_at', now()->toDateString())->sum('amount'),
            'approved_today' => (float) (clone $base)->where('status', 1)->whereDate('updated_at', now()->toDateString())->sum('amount'),
        ];
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->withdrawalQuery())
            ->poll('30s')
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('user_display')
                    ->label('Usuário')
                    ->state(fn (Withdrawal $record): string => $this->userName($record))
                    ->description(fn (Withdrawal $record): ?string => $record->user?->email)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    })
                    ->weight('bold'),

                TextColumn::make('amount')
                    ->label('Valor')
                    ->sortable()
                    ->badge()
                    ->color(fn (Withdrawal $record): string => match ((int) $record->status) {
                        0 => 'warning',
                        9 => 'info',
                        1 => 'success',
                        2 => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => $this->money($state)),

                TextColumn::make('pix_type')
                    ->label('Tipo Pix')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $this->pixTypeLabel($state))
                    ->searchable(),

                TextColumn::make('pix_key')
                    ->label('Chave Pix')
                    ->copyable()
                    ->copyMessage('Chave Pix copiada.')
                    ->limit(22)
                    ->tooltip(fn (Withdrawal $record): ?string => $record->pix_key),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (Withdrawal $record): string => $this->statusLabel($record->status))
                    ->color(fn (Withdrawal $record): string => $this->statusColor($record->status)),

                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (Withdrawal $record): ?string => $record->created_at?->diffForHumans()),

                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')
                    ->label('Buscar')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Usuário, e-mail, CPF, telefone, Pix ou ID pagamento'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery
                                ->where('pix_key', 'like', "%{$search}%")
                                ->orWhere('payment_id', 'like', "%{$search}%")
                                ->orWhere('cpf', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                                    $userQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                        ->orWhere('cpf', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                                });
                        });
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        0 => 'Pendente',
                        9 => 'Processando',
                        1 => 'Aprovado',
                        2 => 'Cancelado',
                    ])
                    ->placeholder('Todos'),

                SelectFilter::make('pix_type')
                    ->label('Tipo Pix')
                    ->options([
                        'document' => 'CPF/CNPJ',
                        'cpf' => 'CPF',
                        'cnpj' => 'CNPJ',
                        'email' => 'E-mail',
                        'phone' => 'Telefone',
                        'random' => 'Chave aleatória',
                        'evp' => 'Chave aleatória',
                    ])
                    ->placeholder('Todos'),

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

                Filter::make('amount_range')
                    ->label('Faixa de valor')
                    ->form([
                        Forms\Components\TextInput::make('min')->label('Mínimo')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('max')->label('Máximo')->numeric()->prefix('R$'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min'] ?? null, fn (Builder $q, $value) => $q->where('amount', '>=', (float) $value))
                            ->when($data['max'] ?? null, fn (Builder $q, $value) => $q->where('amount', '<=', (float) $value));
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->actions([
                Action::make('info')
                    ->label('Informações')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn (Withdrawal $record) => 'Saque #' . $record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->modalContent(fn (Withdrawal $record) => view('filament.pages.partials.withdrawal-info-modal', [
                        'withdrawal' => $record,
                        'money' => fn ($value) => $this->money($value),
                        'statusLabel' => fn ($value) => $this->statusLabel($value),
                        'pixTypeLabel' => fn ($value) => $this->pixTypeLabel($value),
                    ])),

                Action::make('approve_payment')
                    ->label('Pagar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Withdrawal $record): bool => (int) $record->status === 0)
                    ->form($this->pinForm())
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar pagamento do saque')
                    ->modalDescription(fn (Withdrawal $record): string => $this->paymentDescription($record))
                    ->modalSubmitActionLabel('Confirmar pagamento')
                    ->action(fn (Withdrawal $record, array $data) => $this->approvePayment($record, $data)),

                Action::make('refund_payment')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Withdrawal $record): bool => (int) $record->status === 0)
                    ->form($this->pinForm())
                    ->requiresConfirmation()
                    ->modalHeading('Reembolsar saque')
                    ->modalDescription(fn (Withdrawal $record): string => 'O valor voltará para a carteira de saque do usuário: ' . $this->money($record->amount))
                    ->modalSubmitActionLabel('Confirmar reembolso')
                    ->action(fn (Withdrawal $record, array $data) => $this->refundPayment($record, $data)),

                Action::make('delete')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Forçar exclusão deste saque?')
                    ->modalDescription('Essa ação apaga somente o registro do saque. Não paga, não reembolsa e não altera nenhuma carteira.')
                    ->modalSubmitActionLabel('Sim, excluir registro')
                    ->action(function (Withdrawal $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('Saque excluído')
                            ->body('O registro foi removido. Nenhuma carteira foi alterada.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\BulkAction::make('force_delete_selected')
                        ->label('Excluir selecionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Forçar exclusão dos saques selecionados?')
                        ->modalDescription('Essa ação apaga somente os registros selecionados. Não paga, não reembolsa e não altera nenhuma carteira.')
                        ->modalSubmitActionLabel('Sim, excluir selecionados')
                        ->action(function (Collection $records): void {
                            $count = $records->count();

                            $records->each(function (Withdrawal $record): void {
                                $record->delete();
                            });

                            Notification::make()
                                ->title('Saques excluídos')
                                ->body($count . ' registro(s) removido(s). Nenhuma carteira foi alterada.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Nenhum saque encontrado')
            ->emptyStateDescription('Ajuste os filtros para localizar saques.');
    }

    private function withdrawalQuery(): Builder
    {
        return Withdrawal::query()->with('user');
    }

    private function pinForm(): array
    {
        return [
            Forms\Components\TextInput::make('password')
                ->label('PIN administrativo')
                ->password()
                ->numeric()
                ->length(6)
                ->required(),
        ];
    }

    private function approvePayment(Withdrawal $record, array $data): void
    {
        if (! $this->confirmAdminPin($data)) {
            return;
        }

        try {

            // Paga pelo gateway ativo de saque (settings.saque), não fixo no GeraPix.
            $ok = \App\Services\Gateways\GatewayManager::forWithdrawal()->cashOut($record->id, null);

            if ($ok) {
                // C-02: registra a aprovacao de saque (pagamento enviado ao gateway)
                \App\Support\AdminAudit::log(
                    'withdrawal.approve',
                    $record,
                    ['status' => 0],
                    ['status' => (int) ($record->fresh()->status ?? 1)],
                    'Aprovou/enviou pagamento de saque (valor '.$record->amount.')',
                    $record->user_id
                );

                Notification::make()
                    ->title('Pagamento iniciado')
                    ->body('Pagamento enviado ao gateway. O status será atualizado após confirmação.')
                    ->success()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Falha ao pagar')
                ->body('Não foi possível iniciar o pagamento. Verifique gateway, logs e credenciais.')
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao pagar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function refundPayment(Withdrawal $record, array $data): void
    {
        if (! $this->confirmAdminPin($data)) {
            return;
        }

        try {
            DB::transaction(function () use ($record): void {
                $withdrawal = Withdrawal::query()
                    ->lockForUpdate()
                    ->findOrFail($record->id);

                if ((int) $withdrawal->status !== 0) {
                    throw new \RuntimeException('Esse saque não está pendente.');
                }

                $wallet = Wallet::query()
                    ->where('user_id', $withdrawal->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wallet->balance_withdrawal = (float) $wallet->balance_withdrawal + (float) $withdrawal->amount;
                $wallet->save();

                $withdrawal->status = 2;
                $withdrawal->save();
            });

            // C-02: registra o reembolso de saque (valor devolvido a carteira)
            \App\Support\AdminAudit::log(
                'withdrawal.refund',
                $record,
                ['status' => 0],
                ['status' => 2],
                'Reembolsou saque (valor '.$record->amount.' devolvido ao sacavel)',
                $record->user_id
            );

            Notification::make()
                ->title('Saque reembolsado')
                ->body('O valor voltou para a carteira de saque do usuário.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao reembolsar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function confirmAdminPin(array $data): bool
    {
        if (app(AdminActionGuard::class)->confirm((string) ($data['password'] ?? ''))) {
            return true;
        }

        Notification::make()
            ->title('PIN inválido')
            ->body('O PIN administrativo não confere.')
            ->danger()
            ->send();

        return false;
    }

    private function paymentDescription(Withdrawal $record): string
    {
        return "Usuário: {$this->userName($record)}\n" .
            'Valor: ' . $this->money($record->amount) . "\n" .
            'Pix: ' . ($record->pix_key ?: '-');
    }

    public function userName(Withdrawal $withdrawal): string
    {
        $user = $withdrawal->user;

        if (! $user) {
            return $withdrawal->name ?: 'Usuário';
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : ($user->email ?? 'Usuário');
    }

    public function pixTypeLabel(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'document', 'cpf' => 'CPF',
            'cnpj' => 'CNPJ',
            'email' => 'E-mail',
            'phone', 'telefone' => 'Telefone',
            'random', 'evp' => 'Chave aleatória',
            default => $type ?: '-',
        };
    }

    public function statusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Pendente',
            9 => 'Processando',
            1 => 'Aprovado',
            2 => 'Cancelado',
            default => 'Desconhecido',
        };
    }

    public function statusColor($status): string
    {
        return match ((int) $status) {
            0 => 'warning',
            9 => 'info',
            1 => 'success',
            2 => 'danger',
            default => 'gray',
        };
    }

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}