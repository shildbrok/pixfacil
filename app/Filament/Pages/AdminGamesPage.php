<?php



namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Game;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminGamesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-games-page';

    protected static ?string $title = 'Jogos';
    protected static ?string $navigationLabel = 'Jogos';
    protected static ?string $navigationGroup = 'Catálogo de Jogos';
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'jogos';

    public ?Game $previewGame = null;
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
            ->query($this->query())
            ->deferLoading()
            ->headerActions([
                Tables\Actions\Action::make('create_game')
                    ->label('Novo jogo')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading('Criar jogo')
                    ->form($this->gameFormSchema())
                    ->action(function (array $data): void {
                        $categories = $data['categories'] ?? [];
                        // cover_url NÃO entra no unset: é o normalizePayload que o lê para
                        // resolver a capa. Ele já devolve só as colunas do jogo, então o
                        // resto do $data não vaza para o create/update.
                        unset($data['categories']);

                        $game = Game::query()->create($this->normalizePayload($data));
                        $game->categories()->sync($categories);

                        $this->clearGamesCache();

                        Notification::make()
                            ->title('Jogo criado')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                ImageColumn::make('cover')
                    ->label('Capa')
                    ->getStateUsing(fn (Game $record): ?string => $this->imageUrl($record->cover))
                    ->height(64)
                    ->width(64)
                    ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 14px; background:#0a0a0a;']),

                Tables\Columns\TextColumn::make('game_name')
                    ->label('Jogo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Game $record): string => ($record->provider?->name ?: 'Sem provedor') . ' • ' . ($record->game_code ?: 'sem código')),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categorias')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->placeholder('Sem categoria'),

                Tables\Columns\TextColumn::make('distribution')
                    ->label('Distribuição')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'play_fiver' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('show_home')
                    ->label('Home')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Destaque')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('original')
                    ->label('Original')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('views')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider_id')
                    ->label('Provedor')
                    ->options(fn (): array => Provider::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('categories')
                    ->label('Categoria')
                    ->relationship('categories', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('distribution')
                    ->label('Distribuição')
                    ->options(fn (): array => Game::query()
                        ->whereNotNull('distribution')
                        ->distinct()
                        ->orderBy('distribution')
                        ->pluck('distribution', 'distribution')
                        ->toArray()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '1' => 'Ativos',
                        '0' => 'Inativos',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->booleanFilter($query, 'status', $data)),

                Tables\Filters\SelectFilter::make('show_home')
                    ->label('Home')
                    ->options([
                        '1' => 'Na home',
                        '0' => 'Fora da home',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->booleanFilter($query, 'show_home', $data)),

                Tables\Filters\SelectFilter::make('is_featured')
                    ->label('Destaque')
                    ->options([
                        '1' => 'Destacados',
                        '0' => 'Não destacados',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->booleanFilter($query, 'is_featured', $data)),

                Tables\Filters\Filter::make('without_category')
                    ->label('Sem categoria')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('categories')),

                Tables\Filters\Filter::make('without_cover')
                    ->label('Sem capa')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('cover')->orWhere('cover', ''))),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Game $record) => $this->openPreview($record)),

                Tables\Actions\Action::make('edit_game')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (Game $record): string => 'Editar jogo: ' . $record->game_name)
                    ->fillForm(fn (Game $record): array => $this->fillGameForm($record))
                    ->form($this->gameFormSchema(isEdit: true))
                    ->action(function (Game $record, array $data): void {
                        $categories = $data['categories'] ?? [];
                        // cover_url NÃO entra no unset: era isso que apagava a URL nova um
                        // passo antes do normalizePayload lê-la, fazendo o save manter a
                        // capa antiga e ainda assim notificar "Jogo atualizado".
                        unset($data['categories']);

                        $record->update($this->normalizePayload($data, $record));
                        $record->categories()->sync($categories);

                        $this->clearGamesCache();

                        Notification::make()
                            ->title('Jogo atualizado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggle_home')
                    ->label(fn (Game $record): string => $record->show_home ? 'Remover da home' : 'Mostrar na home')
                    ->icon('heroicon-o-home')
                    ->color(fn (Game $record): string => $record->show_home ? 'warning' : 'success')
                    ->action(function (Game $record): void {
                        $record->update([
                            'show_home' => ! (bool) $record->show_home,
                            'status' => ! (bool) $record->show_home ? 1 : $record->status,
                        ]);

                        $this->clearGamesCache();
                    }),

                Tables\Actions\Action::make('toggle_featured')
                    ->label(fn (Game $record): string => $record->is_featured ? 'Remover destaque' : 'Destacar')
                    ->icon('heroicon-o-star')
                    ->color(fn (Game $record): string => $record->is_featured ? 'warning' : 'success')
                    ->action(function (Game $record): void {
                        $record->update([
                            'is_featured' => ! (bool) $record->is_featured,
                            'status' => ! (bool) $record->is_featured ? 1 : $record->status,
                        ]);

                        $this->clearGamesCache();
                    }),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Game $record): string => $record->status ? 'Desativar' : 'Ativar')
                    ->icon(fn (Game $record): string => $record->status ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Game $record): string => $record->status ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Game $record): void {
                        $record->update(['status' => ! (bool) $record->status]);
                        $this->clearGamesCache();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->after(fn () => $this->clearGamesCache()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('set_categories')
                        ->label('Definir categorias')
                        ->icon('heroicon-m-tag')
                        ->color('primary')
                        ->form([
                            Select::make('categories')
                                ->label('Categorias')
                                ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->preload()
                                ->multiple()
                                ->required(),

                            Radio::make('strategy')
                                ->label('Como aplicar')
                                ->options([
                                    'append' => 'Adicionar às existentes',
                                    'replace' => 'Substituir existentes',
                                ])
                                ->default('append')
                                ->inline()
                                ->required(),
                        ])
                        ->action(fn (EloquentCollection $records, array $data) => $this->bulkSetCategories($records, $data))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('clear_categories')
                        ->label('Limpar categorias')
                        ->icon('heroicon-m-trash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            $records->each(fn (Game $game) => $game->categories()->sync([]));
                            $this->clearGamesCache();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('activate_home')
                        ->label('Ativar home')
                        ->icon('heroicon-m-home')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdate($records, ['show_home' => 1, 'status' => 1])),

                    Tables\Actions\BulkAction::make('deactivate_home')
                        ->label('Desativar home')
                        ->icon('heroicon-m-home')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdate($records, ['show_home' => 0])),

                    Tables\Actions\BulkAction::make('activate_featured')
                        ->label('Ativar destaque')
                        ->icon('heroicon-m-star')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdate($records, ['is_featured' => 1, 'status' => 1])),

                    Tables\Actions\BulkAction::make('deactivate_featured')
                        ->label('Desativar destaque')
                        ->icon('heroicon-m-star')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdate($records, ['is_featured' => 0])),

                    Tables\Actions\BulkAction::make('activate_games')
                        ->label('Ativar jogos')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdate($records, ['status' => 1])),

                    Tables\Actions\BulkAction::make('deactivate_games')
                        ->label('Desativar jogos')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (EloquentCollection $records) => $this->bulkUpdate($records, ['status' => 0])),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionados')
                        ->after(fn () => $this->clearGamesCache()),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function query(): Builder
    {
        return Game::query()->with(['provider', 'categories']);
    }

    private function gameFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Prévia no catálogo')
                ->description('O card do frontend usa capa, nome, provedor, status, destaque, home e categorias.')
                ->schema([
                    Placeholder::make('preview_title')
                        ->label('Card')
                        ->content(fn (Get $get): HtmlString => new HtmlString(
                            '<div style="display:grid;gap:6px;color:#d4d4d4;font-size:12px">'
                            . '<div><strong style="color:#fff">' . e((string) ($get('game_name') ?: 'Nome do jogo')) . '</strong></div>'
                            . '<div>Provedor: ' . e($this->providerName((int) ($get('provider_id') ?: 0))) . '</div>'
                            . '<div><span style="display:inline-flex;padding:4px 9px;border-radius:999px;background:rgba(34,197,94,.14);color:#22c55e;font-weight:900">' . ((bool) $get('status') ? 'Ativo' : 'Inativo') . '</span></div>'
                            . '</div>'
                        )),

                    Placeholder::make('catalog_info')
                        ->label('Regras importantes')
                        ->content(new HtmlString('
                            <div style="display:grid;gap:6px;color:#d4d4d4;font-size:12px;line-height:1.55">
                                <div><strong style="color:#fff">Home/Destaque:</strong> ao ativar, o jogo também fica ativo.</div>
                                <div><strong style="color:#fff">Categorias:</strong> são salvas na tabela <code style="color:#fdba74">category_game</code>.</div>
                                <div><strong style="color:#fff">PlayFiver:</strong> game_id/game_code precisam bater com o agregador.</div>
                                <div><strong style="color:#fff">Cache:</strong> ao salvar, os caches do catálogo são limpos.</div>
                            </div>
                        '))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Informações do jogo')
                ->schema([
                    Select::make('provider_id')
                        ->label('Provedor')
                        ->options(fn (): array => Provider::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('game_name')
                        ->label('Nome do jogo')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(191),

                    TextInput::make('views')
                        ->label('Visualizações')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make('game_id')
                        ->label('ID do jogo')
                        ->required()
                        ->maxLength(191),

                    TextInput::make('game_code')
                        ->label('Código do jogo')
                        ->required()
                        ->maxLength(191),

                    Textarea::make('description')
                        ->label('Descrição')
                        ->rows(3)
                        ->columnSpanFull(),

                    Select::make('categories')
                        ->label('Categorias')
                        ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Configurações técnicas')
                ->schema([
                    TextInput::make('game_type')
                        ->label('Tipo')
                        ->placeholder('Slots, Live, Crash...')
                        ->maxLength(191),

                    TextInput::make('technology')
                        ->label('Tecnologia')
                        ->maxLength(191),

                    TextInput::make('rtp')
                        ->label('RTP')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),

                    TextInput::make('distribution')
                        ->label('Distribuição')
                        ->placeholder('play_fiver')
                        ->maxLength(191),

                    Toggle::make('has_lobby')
                        ->label('Tem lobby'),

                    Toggle::make('is_mobile')
                        ->label('Mobile'),

                    Toggle::make('has_freespins')
                        ->label('Freespins'),

                    Toggle::make('has_tables')
                        ->label('Tem mesas'),

                    Toggle::make('only_demo')
                        ->label('Somente demo'),
                ])
                ->columns(3),

            Section::make('Exibição')
                ->schema([
                    Toggle::make('show_home')
                        ->label('Mostrar na home')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set, $state) => $state ? $set('status', true) : null),

                    Toggle::make('is_featured')
                        ->label('Destacar')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set, $state) => $state ? $set('status', true) : null),

                    Toggle::make('status')
                        ->label('Ativo')
                        ->default(true)
                        ->required(),

                    Toggle::make('original')
                        ->label('Original')
                        ->default(false),
                ])
                ->columns(4),

            Section::make('Capa do jogo')
                ->description('Use upload local ou URL externa da capa do agregador.')
                ->schema([
                    Select::make('cover_mode')
                        ->label('Origem da capa')
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

                    // Precisa ser dehydratado: o antigo ->dehydrated(false) copiava a URL
                    // para o 'cover' via afterStateUpdated, mas o 'cover' fica hidden neste
                    // modo — e campo hidden NÃO é dehydratado. A URL não chegava ao ->action()
                    // e o normalizePayload caía no cover antigo, sem erro nenhum.
                    TextInput::make('cover_url')
                        ->label('URL da imagem')
                        ->placeholder('https://exemplo.com/capa.webp')
                        ->visible(fn (Get $get): bool => $get('cover_mode') === 'url')
                        ->required(fn (Get $get): bool => $get('cover_mode') === 'url')
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull(),

                    FileUpload::make('cover')
                        ->label('Capa')
                        ->image()
                        ->imageEditor()
                        ->visible(fn (Get $get): bool => $get('cover_mode') === 'upload')
                        ->required(fn (Get $get): bool => ! $isEdit && $get('cover_mode') === 'upload')
                        // Helper::upload devolve false quando recusa o arquivo (>4MB, mime
                        // inválido, disco sem permissão de escrita). O antigo ['path'] ?? null
                        // transformava isso em null: o form salvava "com sucesso" e mantinha a
                        // capa antiga, sem dizer nada. Falhar aqui é o que torna o erro visível.
                        ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                            $uploaded = \Helper::upload($file);

                            if (! is_array($uploaded) || blank($uploaded['path'] ?? null)) {
                                Notification::make()
                                    ->danger()
                                    ->title('Não foi possível enviar a capa')
                                    ->body('O arquivo foi recusado. Use JPG, PNG, WEBP ou GIF de até 4MB. Se o problema continuar, é permissão de escrita em public/storage/uploads.')
                                    ->persistent()
                                    ->send();

                                throw new Halt();
                            }

                            return $uploaded['path'];
                        })
                        ->helperText('Tamanho recomendado: 300x350. JPG, PNG, WEBP ou GIF de até 4MB.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    private function fillGameForm(Game $record): array
    {
        $coverIsUrl = filled($record->cover) && Str::startsWith($record->cover, ['http://', 'https://']);

        return [
            'provider_id' => $record->provider_id,
            'game_name' => $record->game_name,
            'views' => $record->views,
            'game_id' => $record->game_id,
            'game_code' => $record->game_code,
            'description' => $record->description,
            'categories' => $record->categories()->pluck('categories.id')->all(),
            'game_type' => $record->game_type,
            'technology' => $record->technology,
            'rtp' => $record->rtp,
            'distribution' => $record->distribution,
            'has_lobby' => (bool) $record->has_lobby,
            'is_mobile' => (bool) $record->is_mobile,
            'has_freespins' => (bool) $record->has_freespins,
            'has_tables' => (bool) $record->has_tables,
            'only_demo' => (bool) $record->only_demo,
            'show_home' => (bool) $record->show_home,
            'is_featured' => (bool) $record->is_featured,
            'status' => (bool) $record->status,
            'original' => (bool) $record->original,
            'cover_mode' => $coverIsUrl ? 'url' : 'upload',
            'cover_url' => $coverIsUrl ? $record->cover : null,
            'cover' => $coverIsUrl ? null : $this->normalizeFileUploadStateForForm($record->cover),
        ];
    }

    private function normalizePayload(array $data, ?Game $record = null): array
    {
        // Só um dos dois chega em $data: o campo do outro modo fica hidden e o
        // Filament não dehydrata campo hidden. Sem match, cai na capa atual.
        $cover = filled($data['cover_url'] ?? null)
            ? trim((string) $data['cover_url'])
            : $this->normalizeUploadedPath($data['cover'] ?? null);

        $cover = $cover ?: $record?->cover;

        return [
            'provider_id' => $data['provider_id'] ?? null,
            'game_name' => trim((string) ($data['game_name'] ?? '')),
            'views' => (int) ($data['views'] ?? 0),
            'game_id' => trim((string) ($data['game_id'] ?? '')),
            'game_code' => trim((string) ($data['game_code'] ?? '')),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'game_type' => filled($data['game_type'] ?? null) ? trim((string) $data['game_type']) : null,
            'technology' => filled($data['technology'] ?? null) ? trim((string) $data['technology']) : null,
            'rtp' => filled($data['rtp'] ?? null) ? (int) $data['rtp'] : null,
            'distribution' => filled($data['distribution'] ?? null) ? trim((string) $data['distribution']) : null,
            'has_lobby' => (bool) ($data['has_lobby'] ?? false),
            'is_mobile' => (bool) ($data['is_mobile'] ?? false),
            'has_freespins' => (bool) ($data['has_freespins'] ?? false),
            'has_tables' => (bool) ($data['has_tables'] ?? false),
            'only_demo' => (bool) ($data['only_demo'] ?? false),
            'show_home' => (bool) ($data['show_home'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'status' => (bool) ($data['status'] ?? true),
            'original' => (bool) ($data['original'] ?? false),
            'cover' => $cover,
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

    public function bulkSetCategories(EloquentCollection $records, array $data): void
    {
        $categoryIds = $data['categories'] ?? [];
        $strategy = $data['strategy'] ?? 'append';

        $records->each(function (Game $game) use ($categoryIds, $strategy): void {
            if ($strategy === 'replace') {
                $game->categories()->sync($categoryIds);
            } else {
                $game->categories()->syncWithoutDetaching($categoryIds);
            }
        });

        $this->clearGamesCache();

        Notification::make()
            ->title('Categorias aplicadas')
            ->success()
            ->send();
    }

    public function bulkUpdate(EloquentCollection $records, array $payload): void
    {
        $records->each(fn (Game $game) => $game->update($payload));
        $this->clearGamesCache();

        Notification::make()
            ->title('Jogos atualizados')
            ->success()
            ->send();
    }

    public function openPreview(Game $record): void
    {
        $this->previewGame = $record->load(['provider', 'categories']);
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->previewGame = null;
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

    public function providerName(?int $id): string
    {
        if (! $id) {
            return 'Sem provedor';
        }

        return (string) (Provider::query()->whereKey($id)->value('name') ?: 'Provedor #' . $id);
    }

    public function stats(): array
    {
        // Cacheado por 30s: o cabeçalho (@php $stats = $this->stats()) roda a cada
        // interação da tabela (paginar/ordenar/buscar); sem cache eram 11 counts
        // sobre 3k+ jogos por request. clearGamesCache() invalida na hora após edições.
        return cache()->remember('admin:games:stats:v1', 30, function (): array {
            return [
                'total' => Game::query()->count(),
                'active' => Game::query()->where('status', 1)->count(),
                'inactive' => Game::query()->where('status', 0)->count(),
                'home' => Game::query()->where('show_home', 1)->count(),
                'featured' => Game::query()->where('is_featured', 1)->count(),
                'original' => Game::query()->where('original', 1)->count(),
                'without_category' => Game::query()->whereDoesntHave('categories')->count(),
                'without_cover' => Game::query()->where(fn (Builder $q) => $q->whereNull('cover')->orWhere('cover', ''))->count(),
                'providers' => Provider::query()->count(),
                'category_links' => $this->categoryLinksTotal(),
                'last_update' => Game::query()->latest('updated_at')->value('updated_at'),
            ];
        });
    }

    public function categoryLinksTotal(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('category_game')) {
                return (int) DB::table('category_game')->count();
            }

            if (DB::getSchemaBuilder()->hasTable('category_games')) {
                return (int) DB::table('category_games')->count();
            }

            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function clearGamesCache(): void
    {
        cache()->forget('admin:games:stats:v1');

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
            'pf:v1:categories:list',
        ] as $key) {
            Cache::forget($key);
        }

        Cache::put('asset_version', now()->timestamp, now()->addDays(30));
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
