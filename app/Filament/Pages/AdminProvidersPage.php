<?php



namespace App\Filament\Pages;

use App\Models\Game;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminProvidersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-providers-page';

    protected static ?string $title = 'Provedores de Jogos';
    protected static ?string $navigationLabel = 'Provedores de Jogos';
    protected static ?string $navigationGroup = 'Catálogo de Jogos';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'provedores-de-jogos';

    public ?Provider $previewProvider = null;
    public bool $showPreviewModal = false;

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

            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->headerActions([
                Tables\Actions\Action::make('create_provider')
                    ->label('Novo provedor')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading('Criar provedor de jogos')
                    ->form($this->providerFormSchema())
                    ->action(function (array $data): void {
                        Provider::query()->create($this->normalizePayload($data));
                        $this->clearProviderCache();

                        Notification::make()
                            ->title('Provedor criado')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                ImageColumn::make('cover')
                    ->label('Card PixFácil')
                    ->getStateUsing(fn (Provider $record): ?string => $this->imageUrl($record->pixfacil_home_cover ?: $record->cover))
                    ->height(54)
                    ->width(86)
                    ->extraImgAttributes(['style' => 'object-fit: contain; border-radius: 14px; background:#020617;']),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Provedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Provider $record): string => strtoupper((string) $record->code) . ' • ' . ($record->distribution ?: 'sem distribuição')),

                Tables\Columns\TextColumn::make('games_total')
                    ->label('Jogos')
                    ->state(fn (Provider $record): int => $this->gamesTotal($record))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('games_active')
                    ->label('Ativos')
                    ->state(fn (Provider $record): int => $this->gamesActive($record))
                    ->alignCenter()
                    ->color('success'),

                Tables\Columns\TextColumn::make('games_home')
                    ->label('Home')
                    ->state(fn (Provider $record): int => $this->gamesHome($record))
                    ->alignCenter()
                    ->color('info'),

                Tables\Columns\TextColumn::make('views')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '1' => 'Ativos',
                        '0' => 'Inativos',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->booleanFilter($query, 'status', $data)),

                Tables\Filters\SelectFilter::make('distribution')
                    ->label('Distribuição')
                    ->options(fn (): array => Provider::query()
                        ->whereNotNull('distribution')
                        ->distinct()
                        ->orderBy('distribution')
                        ->pluck('distribution', 'distribution')
                        ->toArray()),

                Tables\Filters\Filter::make('without_cover')
                    ->label('Sem logo')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('cover')->orWhere('cover', ''))),

                Tables\Filters\Filter::make('without_games')
                    ->label('Sem jogos')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('gamesAll')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Provider $record) => $this->openPreview($record)),

                Tables\Actions\Action::make('edit_provider')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (Provider $record): string => 'Editar provedor: ' . $record->name)
                    ->fillForm(fn (Provider $record): array => [
                        'code' => $record->code,
                        'name' => $record->name,
                        'cover_mode' => filled($record->cover) && Str::startsWith($record->cover, ['http://', 'https://']) ? 'url' : 'upload',
                        'cover_url' => filled($record->cover) && Str::startsWith($record->cover, ['http://', 'https://']) ? $record->cover : null,
                        'cover' => filled($record->cover) && ! Str::startsWith($record->cover, ['http://', 'https://']) ? $this->normalizeFileUploadStateForForm($record->cover) : null,
                        'pixfacil_home_cover' => filled($record->pixfacil_home_cover) ? $this->normalizeFileUploadStateForForm($record->pixfacil_home_cover) : null,
                        'status' => (bool) $record->status,
                        'distribution' => $record->distribution,
                        'views' => $record->views,
                    ])
                    ->form($this->providerFormSchema(isEdit: true))
                    ->action(function (Provider $record, array $data): void {
                        unset($data['cover_mode'], $data['cover_url']);
                        $record->update($this->normalizePayload($data, $record));
                        $this->clearProviderCache();

                        Notification::make()
                            ->title('Provedor atualizado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Provider $record): string => $record->status ? 'Desativar' : 'Ativar')
                    ->icon(fn (Provider $record): string => $record->status ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Provider $record): string => $record->status ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Provider $record): void {
                        $record->update(['status' => ! (bool) $record->status]);
                        $this->clearProviderCache();
                    }),

                Tables\Actions\Action::make('activate_games')
                    ->label('Ativar jogos')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Ativa todos os jogos deste provedor.')
                    ->action(function (Provider $record): void {
                        Game::query()->where('provider_id', $record->id)->update(['status' => 1]);
                        $this->clearProviderCache();

                        Notification::make()
                            ->title('Jogos ativados')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('deactivate_games')
                    ->label('Desativar jogos')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Desativa todos os jogos deste provedor.')
                    ->action(function (Provider $record): void {
                        Game::query()->where('provider_id', $record->id)->update(['status' => 0]);
                        $this->clearProviderCache();

                        Notification::make()
                            ->title('Jogos desativados')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->before(function (Provider $record): void {
                        if ($this->gamesTotal($record) > 0) {
                            Notification::make()
                                ->title('Provedor possui jogos vinculados')
                                ->body('Excluir o provedor pode deixar jogos sem referência. Revise antes de confirmar.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->after(fn () => $this->clearProviderCache()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate_providers')
                        ->label('Ativar provedores')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdateProviders($records, ['status' => 1])),

                    Tables\Actions\BulkAction::make('deactivate_providers')
                        ->label('Desativar provedores')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdateProviders($records, ['status' => 0])),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionados')
                        ->after(fn () => $this->clearProviderCache()),
                ]),
            ])
            ->defaultSort('name')
            ->striped();
    }

    private function query(): Builder
    {
        return Provider::query();
    }

    private function providerFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Dados do provedor')
                ->schema([
                    TextInput::make('code')
                        ->label('Código do provedor')
                        ->placeholder('Ex.: PGSOFT')
                        ->required()
                        ->maxLength(80)
                        ->live(onBlur: true)
                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                        ->extraInputAttributes([
                            'style' => 'text-transform: uppercase',
                            'autocomplete' => 'off',
                            'spellcheck' => 'false',
                        ]),

                    TextInput::make('name')
                        ->label('Nome do provedor')
                        ->placeholder('Ex.: PG Soft')
                        ->required()
                        ->maxLength(191)
                        ->live(onBlur: true),

                    TextInput::make('distribution')
                        ->label('Distribuição')
                        ->placeholder('play_fiver')
                        ->maxLength(191)
                        ->helperText('Use play_fiver para provedores sincronizados pelo agregador.'),

                    TextInput::make('views')
                        ->label('Visualizações')
                        ->numeric()
                        ->inputMode('numeric')
                        ->default(0)
                        ->minValue(0),

                    Toggle::make('status')
                        ->label('Provedor ativo')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(3),

            Section::make('Logo / capa do provedor')
                ->description('Use upload local ou URL externa recebida do agregador.')
                ->schema([
                    Forms\Components\Select::make('cover_mode')
                        ->label('Origem da imagem')
                        ->options([
                            'upload' => 'Enviar imagem',
                            'url' => 'Usar URL externa',
                        ])
                        ->default('upload')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if ($state === 'upload' && filled($get('cover_url'))) {
                                $set('cover_url', null);
                            }
                        }),

                    TextInput::make('cover_url')
                        ->label('URL da imagem')
                        ->placeholder('https://exemplo.com/provider.webp')
                        ->visible(fn (Get $get): bool => $get('cover_mode') === 'url')
                        ->required(fn (Get $get): bool => $get('cover_mode') === 'url')
                        ->url()
                        ->maxLength(2048)
                        ->dehydrated(false)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('cover', $state))
                        ->columnSpanFull(),

                    FileUpload::make('cover')
                        ->label('Logo / imagem')
                        ->image()
                        ->imageEditor()
                        ->visible(fn (Get $get): bool => $get('cover_mode') === 'upload')
                        ->required(fn (Get $get): bool => ! $isEdit && $get('cover_mode') === 'upload')
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null)
                        ->helperText('Recomendado: imagem horizontal ou logo PNG/WebP.')
                        ->columnSpanFull(),

                    FileUpload::make('pixfacil_home_cover')
                        ->label('Card horizontal PixFácil')
                        ->image()
                        ->imageEditor()
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null)
                        ->helperText('Opcional. Recomendado: 1200x500 (aprox. 2,4:1). Usado somente no tema PixFácil.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    private function normalizePayload(array $data, ?Provider $record = null): array
    {
        return [
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'cover' => $this->normalizeUploadedPath($data['cover'] ?? null) ?: $record?->cover,
            'pixfacil_home_cover' => $this->normalizeUploadedPath($data['pixfacil_home_cover'] ?? null) ?: $record?->pixfacil_home_cover,
            'status' => (bool) ($data['status'] ?? true),
            'distribution' => filled($data['distribution'] ?? null) ? trim((string) $data['distribution']) : null,
            'views' => (int) ($data['views'] ?? 0),
        ];
    }

    private function normalizeFileUploadStateForForm(mixed $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        if (is_string($value)) {
            return [$value];
        }

        return null;
    }

    private function normalizeUploadedPath(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_string($value)) {
            return ltrim($value, '/');
        }

        if ($value instanceof TemporaryUploadedFile) {
            $uploaded = \Helper::upload($value);

            return is_array($uploaded) ? ($uploaded['path'] ?? null) : null;
        }

        if (is_array($value)) {
            $first = reset($value);

            return $this->normalizeUploadedPath($first);
        }

        return null;
    }

    private function booleanFilter(Builder $query, string $column, array $data): Builder
    {
        if (($data['value'] ?? null) !== null && ($data['value'] ?? '') !== '') {
            return $query->where($column, (int) $data['value']);
        }

        return $query;
    }

    public function openPreview(Provider $record): void
    {
        $this->previewProvider = $record;
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->previewProvider = null;
        $this->showPreviewModal = false;
    }

    public function imageUrl(?string $image): ?string
    {
        if (! filled($image)) {
            return null;
        }

        $image = ltrim((string) $image, '/');

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, 'storage/')) {
            return asset($image);
        }

        if (str_starts_with($image, 'uploads/')) {
            return asset('storage/' . $image);
        }

        return asset('storage/' . $image);
    }

    public function gamesTotal(Provider $provider): int
    {
        return (int) Game::query()->where('provider_id', $provider->id)->count();
    }

    public function gamesActive(Provider $provider): int
    {
        return (int) Game::query()->where('provider_id', $provider->id)->where('status', 1)->count();
    }

    public function gamesHome(Provider $provider): int
    {
        return (int) Game::query()->where('provider_id', $provider->id)->where('show_home', 1)->count();
    }

    public function gamesFeatured(Provider $provider): int
    {
        return (int) Game::query()->where('provider_id', $provider->id)->where('is_featured', 1)->count();
    }

    public function bulkUpdateProviders(EloquentCollection $records, array $payload): void
    {
        $records->each(fn (Provider $provider) => $provider->update($payload));
        $this->clearProviderCache();

        Notification::make()
            ->title('Provedores atualizados')
            ->success()
            ->send();
    }

    public function stats(): array
    {
        return cache()->remember('admin:providers:stats:v1', 30, fn (): array => [
            'total' => Provider::query()->count(),
            'active' => Provider::query()->where('status', 1)->count(),
            'inactive' => Provider::query()->where('status', 0)->count(),
            'without_cover' => Provider::query()->where(fn (Builder $q) => $q->whereNull('cover')->orWhere('cover', ''))->count(),
            'play_fiver' => Provider::query()->where('distribution', 'play_fiver')->count(),
            'games_total' => Game::query()->count(),
            'games_active' => Game::query()->where('status', 1)->count(),
            'games_home' => Game::query()->where('show_home', 1)->count(),
            'last_update' => Provider::query()->latest('updated_at')->value('updated_at'),
        ]);
    }

    public function clearProviderCache(): void
    {
        if (method_exists(Game::class, 'clearCatalogCaches')) {
            Game::clearCatalogCaches();
        }

        foreach ([
            'games',
            'games.all',
            'games.home',
            'games.featured',
            'games.providers',
            'games.categories',
            'casino.games',
            'casino.games.home',
            'casino.games.featured',
            'api.games',
            'api.games.home',
            'api.games.featured',
            'home_games',
            'featured_games',
            'pf:v6:providers_with_games_priority_min',
            'pf:v7:providers_with_games_priority_min',
            'home:providers:v1',
            'home:providers:v2',
            'home:providers:v3',
        ] as $key) {
            Cache::forget($key);
        }

        Cache::put('asset_version', now()->timestamp, now()->addDays(30));
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}