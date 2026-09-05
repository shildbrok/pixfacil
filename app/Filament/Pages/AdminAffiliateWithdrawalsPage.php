<?php



namespace App\Filament\Pages;

use App\Helpers\Core;
use App\Http\Controllers\Api\Profile\WalletController;
use App\Models\AffiliateWithdraw;
use App\Models\Wallet;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminAffiliateWithdrawalsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-affiliate-withdrawals-page';

    protected static ?string $title = 'Saques de Afiliados';
    protected static ?string $navigationLabel = 'Saques de Afiliados';
    protected static ?string $navigationGroup = 'Saques';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'saques-de-afiliados';

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
        $pending = AffiliateWithdraw::query()->where('status', 0)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $pending = AffiliateWithdraw::query()->where('status', 0)->count();

        return $pending >= 10 ? 'danger' : ($pending >= 3 ? 'warning' : 'success');
    }

    public function stats(): array
    {
        return cache()->remember('admin:affiliate-withdrawals:stats:v1', 30, function (): array {
        $base = AffiliateWithdraw::query();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 0)->count(),
            'processing' => (clone $base)->where('status', 9)->count(),
            'approved' => (clone $base)->where('status', 1)->count(),
            'cancelled' => (clone $base)->where('status', 2)->count(),
            'pending_amount' => (float) (clone $base)->where('status', 0)->sum('amount'),
            'today_amount' => (float) (clone $base)->whereDate('created_at', now()->toDateString())->sum('amount'),
            'approved_today' => (float) (clone $base)->where('status', 1)->whereDate('updated_at', now()->toDateString())->sum('amount'),
            'commission_balance' => (float) Wallet::query()->sum('refer_rewards'),
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
                    ->label('Afiliado')
                    ->state(fn (AffiliateWithdraw $record): string => $this->userName($record))
                    ->description(fn (AffiliateWithdraw $record): ?string => $record->user?->email)
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
                    ->color(fn (AffiliateWithdraw $record): string => match ((int) $record->status) {
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
                    ->tooltip(fn (AffiliateWithdraw $record): ?string => $record->pix_key),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (AffiliateWithdraw $record): string => $this->statusLabel($record->status))
                    ->color(fn (AffiliateWithdraw $record): string => $this->statusColor($record->status)),

                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (AffiliateWithdraw $record): ?string => $record->created_at?->diffForHumans()),

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
                            ->label('Afiliado, e-mail, CPF, telefone, Pix ou ID pagamento'),
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
                    ->modalHeading(fn (AffiliateWithdraw $record) => 'Saque de afiliado #' . $record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->modalContent(fn (AffiliateWithdraw $record) => view('filament.pages.partials.affiliate-withdrawal-info-modal', [
                        'withdrawal' => $record,
                        'money' => fn ($value) => $this->money($value),
                        'statusLabel' => fn ($value) => $this->statusLabel($value),
                        'pixTypeLabel' => fn ($value) => $this->pixTypeLabel($value),
                    ])),

                Action::make('approve_payment')
                    ->label('Pagar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AffiliateWithdraw $record): bool => (int) $record->status === 0)
                    ->form($this->pinForm())
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar pagamento do saque de afiliado')
                    ->modalDescription(fn (AffiliateWithdraw $record): string => $this->paymentDescription($record))
                    ->modalSubmitActionLabel('Confirmar pagamento')
                    ->action(fn (AffiliateWithdraw $record, array $data) => $this->approvePayment($record, $data)),

                Action::make('refund_payment')
                    ->label('Reembolsar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (AffiliateWithdraw $record): bool => (int) $record->status === 0)
                    ->form($this->pinForm())
                    ->requiresConfirmation()
                    ->modalHeading('Reembolsar saque de afiliado')
                    ->modalDescription(fn (AffiliateWithdraw $record): string => 'O valor voltará para a carteira de comissão do afiliado: ' . $this->money($record->amount))
                    ->modalSubmitActionLabel('Confirmar reembolso')
                    ->action(fn (AffiliateWithdraw $record, array $data) => $this->refundPayment($record, $data)),

                Action::make('delete')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Forçar exclusão deste saque?')
                    ->modalDescription('Essa ação apaga somente o registro do saque de afiliado. Não paga, não reembolsa e não altera carteira.')
                    ->modalSubmitActionLabel('Sim, excluir registro')
                    ->action(function (AffiliateWithdraw $record): void {
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
                        ->modalDescription('Essa ação apaga somente os registros selecionados. Não paga, não reembolsa e não altera carteiras.')
                        ->modalSubmitActionLabel('Sim, excluir selecionados')
                        ->action(function (Collection $records): void {
                            $count = $records->count();

                            $records->each(function (AffiliateWithdraw $record): void {
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
            ->emptyStateHeading('Nenhum saque de afiliado encontrado')
            ->emptyStateDescription('Ajuste os filtros para localizar saques de afiliados.');
    }

    private function withdrawalQuery(): Builder
    {
        return AffiliateWithdraw::query()->with('user');
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

    private function approvePayment(AffiliateWithdraw $record, array $data): void
    {
        if (! $this->confirmAdminPin($data)) {
            return;
        }

        try {

            $ok = app(WalletController::class)->pixCashOutGeraPix($record->id, 'afiliado');

            if ($ok) {
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

    private function refundPayment(AffiliateWithdraw $record, array $data): void
    {
        if (! $this->confirmAdminPin($data)) {
            return;
        }

        try {
            DB::transaction(function () use ($record): void {
                $withdrawal = AffiliateWithdraw::query()
                    ->lockForUpdate()
                    ->findOrFail($record->id);

                if ((int) $withdrawal->status !== 0) {
                    throw new \RuntimeException('Esse saque de afiliado não está pendente.');
                }

                $wallet = Wallet::query()
                    ->where('user_id', $withdrawal->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wallet->refer_rewards = (float) $wallet->refer_rewards + (float) $withdrawal->amount;
                $wallet->save();

                $withdrawal->status = 2;
                $withdrawal->save();
            });

            Notification::make()
                ->title('Saque reembolsado')
                ->body('O valor voltou para a carteira de comissão do afiliado.')
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

    private function paymentDescription(AffiliateWithdraw $record): string
    {
        return "Afiliado: {$this->userName($record)}\n" .
            'Valor: ' . $this->money($record->amount) . "\n" .
            'Pix: ' . ($record->pix_key ?: '-');
    }

    public function userName(AffiliateWithdraw $withdrawal): string
    {
        $user = $withdrawal->user;

        if (! $user) {
            return $withdrawal->name ?: 'Afiliado';
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : ($user->email ?? 'Afiliado');
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
