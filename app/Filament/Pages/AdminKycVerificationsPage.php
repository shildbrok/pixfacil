<?php



namespace App\Filament\Pages;

use App\Models\Verificacao;
use App\Support\AdminActionGuard;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminKycVerificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-kyc-verifications-page';

    protected static ?string $title = 'Verificações KYC';
    protected static ?string $navigationLabel = 'Verificações KYC';
    protected static ?string $navigationGroup = 'KYC e Compliance';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'verificacoes-kyc';

    public ?Verificacao $selectedVerification = null;
    public bool $showVerifyModal = false;

    public ?string $adminPin = null;
    public ?string $rejectReason = null;

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
                    ->formatStateUsing(fn ($state, Verificacao $record) => $record->user?->name ?: $record->nome_completo ?: 'Sem nome')
                    ->description(fn (Verificacao $record) => $record->user?->email ?: 'Sem e-mail'),

                Tables\Columns\TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $this->formatCpf($state))
                    ->copyable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Verificacao::STATUS_PENDING => 'warning',
                        Verificacao::STATUS_APPROVED => 'success',
                        Verificacao::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => $this->statusLabel($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('aprovado_em')
                    ->label('Finalizado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Verificacao::STATUS_PENDING => 'Em análise',
                        Verificacao::STATUS_APPROVED => 'Aprovado',
                        Verificacao::STATUS_REJECTED => 'Rejeitado',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Enviadas hoje')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->actions([
                Tables\Actions\Action::make('verificar')
                    ->label('Verificar')
                    ->icon('heroicon-o-identification')
                    ->color('primary')
                    ->button()
                    ->action(fn (Verificacao $record) => $this->openVerifyModal($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    private function query(): Builder
    {
        return Verificacao::query()
            ->with('user')
            ->latest('created_at');
    }

    public function openVerifyModal(Verificacao $record): void
    {
        $this->selectedVerification = $record->load('user');
        $this->adminPin = null;
        $this->rejectReason = null;
        $this->showVerifyModal = true;
    }

    public function closeVerifyModal(): void
    {
        $this->showVerifyModal = false;
        $this->selectedVerification = null;
        $this->adminPin = null;
        $this->rejectReason = null;
    }

    public function approveSelectedVerification(): void
    {
        $record = $this->selectedVerification?->fresh();

        if (! $record) {
            Notification::make()
                ->title('Registro não encontrado')
                ->danger()
                ->send();

            return;
        }

        if (! $record->isPending()) {
            Notification::make()
                ->title('Registro já finalizado')
                ->body('Somente verificações em análise podem ser aprovadas.')
                ->warning()
                ->send();

            $this->closeVerifyModal();
            return;
        }

        if (! $this->confirmPin($this->adminPin, 'aprovar a verificação')) {
            return;
        }

        $record->aprovar(auth()->id() ?? 0);

        Notification::make()
            ->title('KYC aprovado')
            ->body('A verificação foi aprovada com sucesso.')
            ->success()
            ->send();

        $this->closeVerifyModal();
        $this->resetTable();
    }

    public function rejectSelectedVerification(): void
    {
        $record = $this->selectedVerification?->fresh();

        if (! $record) {
            Notification::make()
                ->title('Registro não encontrado')
                ->danger()
                ->send();

            return;
        }

        if (! $record->isPending()) {
            Notification::make()
                ->title('Registro já finalizado')
                ->body('Somente verificações em análise podem ser rejeitadas.')
                ->warning()
                ->send();

            $this->closeVerifyModal();
            return;
        }

        $reason = trim((string) $this->rejectReason);

        if ($reason === '') {
            Notification::make()
                ->title('Motivo obrigatório')
                ->body('Informe o motivo da reprovação.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->confirmPin($this->adminPin, 'rejeitar a verificação')) {
            return;
        }

        $record->rejeitar(auth()->id() ?? 0, $reason);

        Notification::make()
            ->title('KYC rejeitado')
            ->body('A verificação foi rejeitada.')
            ->success()
            ->send();

        $this->closeVerifyModal();
        $this->resetTable();
    }

    private function confirmPin(?string $pin, string $action): bool
    {
        if (app(AdminActionGuard::class)->confirm((string) $pin)) {
            return true;
        }

        Notification::make()
            ->title('Acesso negado')
            ->body('Confirme o PIN administrativo para ' . $action . '.')
            ->danger()
            ->send();

        return false;
    }

    public function stats(): array
    {
        return [
            'pending' => Verificacao::query()->where('status', Verificacao::STATUS_PENDING)->count(),
            'approved' => Verificacao::query()->where('status', Verificacao::STATUS_APPROVED)->count(),
            'rejected' => Verificacao::query()->where('status', Verificacao::STATUS_REJECTED)->count(),
            'today' => Verificacao::query()->whereDate('created_at', today())->count(),
            'total' => Verificacao::query()->count(),
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            Verificacao::STATUS_PENDING => 'Em análise',
            Verificacao::STATUS_APPROVED => 'Aprovado',
            Verificacao::STATUS_REJECTED => 'Rejeitado',
            default => $status ?: '—',
        };
    }

    public function statusClass(?string $status): string
    {
        return match ($status) {
            Verificacao::STATUS_PENDING => 'kv-status-warn',
            Verificacao::STATUS_APPROVED => 'kv-status-ok',
            Verificacao::STATUS_REJECTED => 'kv-status-bad',
            default => 'kv-status-muted',
        };
    }

    public function formatCpf(?string $cpf): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf);

        if (strlen($digits) !== 11) {
            return $cpf ?: '—';
        }

        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    public function fileUrl(?Verificacao $record, string $type): ?string
    {
        if (! $record) {
            return null;
        }

        $map = [
            'selfie' => 'selfie_path',
            'frente' => 'doc_frente_path',
            'verso' => 'doc_verso_path',
        ];

        $column = $map[$type] ?? null;

        if (! $column || blank($record->{$column} ?? null)) {
            return null;
        }

        return route('admin.kyc.file', [
            'verificacao' => $record->id,
            'type' => $type,
        ]);
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}