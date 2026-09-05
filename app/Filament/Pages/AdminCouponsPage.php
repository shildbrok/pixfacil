<?php



namespace App\Filament\Pages;

use App\Models\Cupom;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminCouponsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-coupons-page';

    protected static ?string $title = 'Cupons Promocionais';
    protected static ?string $navigationLabel = 'Cupons';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'cupons-promocionais';

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
            ->headerActions([
                Tables\Actions\Action::make('create_coupon')
                    ->label('Novo cupom')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Criar cupom promocional')
                    ->form($this->couponFormSchema())
                    ->action(function (array $data): void {
                        $data = $this->normalizeCouponPayload($data);
                        $data['usos'] = 0;

                        Cupom::query()->create($data);

                        Notification::make()
                            ->title('Cupom criado')
                            ->body('O cupom promocional foi cadastrado com sucesso.')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->formatStateUsing(fn (?string $state): string => strtoupper((string) $state)),

                Tables\Columns\TextColumn::make('valor_bonus')
                    ->label('Bônus')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantidade_uso')
                    ->label('Limite')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('usos')
                    ->label('Usos')
                    ->alignCenter()
                    ->sortable()
                    ->formatStateUsing(fn ($state, Cupom $record): string => ((int) $state) . ' / ' . ((int) $record->quantidade_uso)),

                Tables\Columns\TextColumn::make('status_visual')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Cupom $record): string => $this->couponStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Ativo' => 'success',
                        'Expirado' => 'danger',
                        'Esgotado' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('ativos')
                    ->label('Ativos')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('validade', '>=', now())
                        ->whereColumn('usos', '<', 'quantidade_uso')),

                Tables\Filters\Filter::make('expirados')
                    ->label('Expirados')
                    ->query(fn (Builder $query): Builder => $query->whereDate('validade', '<', now())),

                Tables\Filters\Filter::make('esgotados')
                    ->label('Esgotados')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('usos', '>=', 'quantidade_uso')),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_coupon')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Cupom $record): string => 'Editar cupom ' . strtoupper($record->codigo))
                    ->fillForm(fn (Cupom $record): array => [
                        'codigo' => strtoupper((string) $record->codigo),
                        'valor_bonus' => $record->valor_bonus,
                        'validade' => $record->validade,
                        'quantidade_uso' => $record->quantidade_uso,
                        'usos' => $record->usos,
                    ])
                    ->form($this->couponFormSchema(isEdit: true))
                    ->action(function (Cupom $record, array $data): void {
                        $record->update($this->normalizeCouponPayload($data, isEdit: true));

                        Notification::make()
                            ->title('Cupom atualizado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reset_usos')
                    ->label('Zerar usos')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Cupom $record): bool => (int) $record->usos > 0)
                    ->action(function (Cupom $record): void {
                        $record->update(['usos' => 0]);

                        Notification::make()
                            ->title('Usos zerados')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionados'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    private function query(): Builder
    {
        return Cupom::query();
    }

    private function couponFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Dados do cupom')
                ->description('Configure o código, valor do bônus, validade e limite de uso.')
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código do cupom')
                        ->required()
                        ->maxLength(32)
                        ->unique(table: 'cupons', column: 'codigo', ignoreRecord: $isEdit)
                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                        ->extraInputAttributes([
                            'style' => 'text-transform: uppercase',
                            'autocomplete' => 'off',
                            'spellcheck' => 'false',
                        ])
                        ->suffixAction(
                            FormAction::make('gerar_codigo')
                                ->label('Gerar')
                                ->icon('heroicon-m-sparkles')
                                ->action(fn (Forms\Set $set) => $set('codigo', $this->generateUniqueCode()))
                        )
                        ->helperText('Exemplo: AH423SAKNM. Use o botão gerar para criar um código único.'),

                    TextInput::make('valor_bonus')
                        ->label('Valor do bônus')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->required()
                        ->minValue(0.01),

                    DatePicker::make('validade')
                        ->label('Validade')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(now()->startOfDay()),

                    TextInput::make('quantidade_uso')
                        ->label('Quantidade máxima de usos')
                        ->numeric()
                        ->inputMode('numeric')
                        ->required()
                        ->minValue(1),

                    TextInput::make('usos')
                        ->label('Usos atuais')
                        ->numeric()
                        ->inputMode('numeric')
                        ->default(0)
                        ->minValue(0)
                        ->visible($isEdit)
                        ->helperText('Permite corrigir manualmente a contagem, se necessário.'),
                ])
                ->columns(2),
        ];
    }

    private function normalizeCouponPayload(array $data, bool $isEdit = false): array
    {
        $payload = [
            'codigo' => strtoupper(trim((string) ($data['codigo'] ?? ''))),
            'valor_bonus' => (float) str_replace(',', '.', (string) ($data['valor_bonus'] ?? 0)),
            'validade' => $data['validade'] ?? null,
            'quantidade_uso' => (int) ($data['quantidade_uso'] ?? 1),
        ];

        if ($isEdit && array_key_exists('usos', $data)) {
            $payload['usos'] = max(0, (int) $data['usos']);
        }

        return $payload;
    }

    public function generateUniqueCode(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Cupom::query()->where('codigo', $code)->exists());

        return $code;
    }

    public function couponStatus(Cupom $record): string
    {
        if ($record->validade < now()->startOfDay()) {
            return 'Expirado';
        }

        if ((int) $record->usos >= (int) $record->quantidade_uso) {
            return 'Esgotado';
        }

        return 'Ativo';
    }

    public function stats(): array
    {
        $total = Cupom::query()->count();
        $active = Cupom::query()
            ->whereDate('validade', '>=', now())
            ->whereColumn('usos', '<', 'quantidade_uso')
            ->count();

        $expired = Cupom::query()->whereDate('validade', '<', now())->count();
        $exhausted = Cupom::query()->whereColumn('usos', '>=', 'quantidade_uso')->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'exhausted' => $exhausted,
            'uses' => (int) Cupom::query()->sum('usos'),
            'bonus' => (float) Cupom::query()->sum('valor_bonus'),
            'next_expiration' => Cupom::query()
                ->whereDate('validade', '>=', now())
                ->orderBy('validade')
                ->value('validade'),
        ];
    }

    public function money(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}
