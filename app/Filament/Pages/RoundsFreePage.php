<?php



namespace App\Filament\Pages;

use App\Models\ConfigRoundsFree;
use App\Models\Game;
use App\Models\Provider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class RoundsFreePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.rounds-free-page';

    protected static ?string $title = 'Configuração de Rodadas Grátis';
    protected static ?string $navigationLabel = 'Config. Rodadas Grátis';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'config-rodadas-gratis';

    /**
     * Provedores suportados para rodadas grátis (PlayFiver).
     * Resolvidos pelo NOME para não quebrar quando os provider_id do
     * catálogo mudam após uma reimportação de jogos.
     */
    protected array $allowedProviderNames = ['PRAGMATIC', 'PGSOFT', 'FATPANDA'];

    protected ?array $providerLabelsCache = null;

    protected function providerLabels(): array
    {
        if ($this->providerLabelsCache !== null) {
            return $this->providerLabelsCache;
        }

        return $this->providerLabelsCache = Provider::query()
            ->whereIn('name', $this->allowedProviderNames)
            ->pluck('name', 'id')
            ->all();
    }

    protected function allowedProviderIds(): array
    {
        return array_map('intval', array_keys($this->providerLabels()));
    }

    protected function allowedGamesOptions(): array
    {
        $providers = $this->providerLabels();

        return Game::query()
            ->where('status', 1)
            ->whereIn('provider_id', $this->allowedProviderIds())
            ->orderBy('provider_id')
            ->orderBy('game_name')
            ->get()
            ->mapWithKeys(fn ($game) => [
                $game->game_code => $game->game_name . ' [' . ($providers[$game->provider_id] ?? ('ID ' . $game->provider_id)) . ']',
            ])
            ->toArray();
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    protected function getTableQuery(): Builder
    {
        return ConfigRoundsFree::query()
            ->with('game')
            ->whereHas('game', function (Builder $query) {
                $query->whereIn('provider_id', $this->allowedProviderIds());
            })
            ->latest('id');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
                ->sortable()
                ->toggleable(),

            TextColumn::make('game.game_name')
                ->label('Jogo')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('game', function (Builder $gameQuery) use ($search) {
                        $gameQuery->where('game_name', 'like', "%{$search}%");
                    });
                })
                ->sortable()
                ->wrap()
                ->placeholder('-'),

            TextColumn::make('game.provider_id')
                ->label('Prov.')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->providerLabels()[(int) $state] ?? ('ID ' . $state))
                ->sortable()
                ->placeholder('-'),

            TextColumn::make('game_code')
                ->label('Código')
                ->copyable()
                ->copyMessage('Código copiado com sucesso!')
                ->searchable()
                ->sortable()
                ->limit(24)
                ->tooltip(fn ($record) => $record->game_code),

            TextColumn::make('spins')
                ->label('Rodadas')
                ->badge()
                ->color('warning')
                ->sortable(),

            TextColumn::make('value')
                ->label('Dep. mín.')
                ->money('BRL', true)
                ->sortable(),

            IconColumn::make('status')
                ->label('Ativo')
                ->boolean()
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Criado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->placeholder('Todos')
                ->options([
                    '1' => 'Ativos',
                    '0' => 'Inativos',
                ]),

            SelectFilter::make('game_code')
                ->label('Jogo')
                ->options($this->allowedGamesOptions())
                ->searchable()
                ->placeholder('Todos'),

            SelectFilter::make('provider_id')
                ->label('Provedor')
                ->placeholder('Todos')
                ->options($this->providerLabels())
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('game', function (Builder $gameQuery) use ($data) {
                        $gameQuery->where('provider_id', (int) $data['value']);
                    });
                }),

            Filter::make('created_at')
                ->label('Período')
                ->form([
                    DatePicker::make('from')->label('De'),
                    DatePicker::make('until')->label('Até'),
                ])
                ->columns(2)
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                }),
        ];
    }

    protected function getTableFiltersLayout(): ?FiltersLayout
    {
        return FiltersLayout::AboveContentCollapsible;
    }

    protected function getTableFiltersFormColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('edit_config')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->slideOver()
                ->modalHeading('Editar configuração')
                ->modalWidth('lg')
                ->form($this->configurationFormSchema())
                ->fillForm(fn (ConfigRoundsFree $record) => [
                    'game_code' => $record->game_code,
                    'spins' => (int) $record->spins,
                    'value' => (float) $record->value,
                    'status' => (bool) $record->status,
                ])
                ->action(function (ConfigRoundsFree $record, array $data): void {
                    $record->update([
                        'game_code' => $data['game_code'],
                        'spins' => (int) $data['spins'],
                        'value' => (float) $data['value'],
                        'status' => ! empty($data['status']) ? 1 : 0,
                    ]);

                    Notification::make()
                        ->title('Configuração atualizada')
                        ->body('A regra de rodadas grátis foi atualizada com sucesso.')
                        ->success()
                        ->send();
                }),

            Action::make('toggle_status')
                ->label(fn (ConfigRoundsFree $record) => $record->status ? 'Desativar' : 'Ativar')
                ->icon(fn (ConfigRoundsFree $record) => $record->status ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color(fn (ConfigRoundsFree $record) => $record->status ? 'danger' : 'success')
                ->requiresConfirmation()
                ->action(function (ConfigRoundsFree $record): void {
                    $record->update([
                        'status' => $record->status ? 0 : 1,
                    ]);

                    Notification::make()
                        ->title('Status atualizado')
                        ->success()
                        ->send();
                }),

            DeleteAction::make()
                ->label('Excluir')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation(),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova configuração')
                ->icon('heroicon-o-plus')
                ->slideOver()
                ->modalHeading('Nova configuração de rodadas grátis')
                ->modalWidth('lg')
                ->disableCreateAnother()
                ->form($this->configurationFormSchema())
                ->using(function (array $data) {
                    return ConfigRoundsFree::create([
                        'game_code' => $data['game_code'],
                        'spins' => (int) $data['spins'],
                        'value' => (float) $data['value'],
                        'status' => ! empty($data['status']) ? 1 : 0,
                    ]);
                }),
        ];
    }

    private function configurationFormSchema(): array
    {
        return [
            Select::make('game_code')
                ->label('Jogo')
                ->options($this->allowedGamesOptions())
                ->searchable()
                ->preload()
                ->required()
                ->rules([
                    'required',
                    Rule::exists('games', 'game_code')->where(function ($query) {
                        $query->where('status', 1)
                            ->whereIn('provider_id', $this->allowedProviderIds());
                    }),
                ]),

            TextInput::make('spins')
                ->label('Rodadas padrão')
                ->numeric()
                ->required()
                ->rules(['required', 'integer', 'min:1', 'max:1000'])
                ->helperText('PlayFiver: 1 a 1000. Cliente padrão: normalmente até 50; volume liberado: acima disso.')
                ->placeholder('Ex.: 50'),

            TextInput::make('value')
                ->label('Depósito mínimo (R$)')
                ->numeric()
                ->required()
                ->rules(['required', 'numeric', 'min:0'])
                ->placeholder('Ex.: 50'),

            Toggle::make('status')
                ->label('Configuração ativa')
                ->default(true),
        ];
    }
}