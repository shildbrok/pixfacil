<?php



namespace App\Filament\Pages;

use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminGameCategoriesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-game-categories-page';

    protected static ?string $title = 'Categorias de Jogos';
    protected static ?string $navigationLabel = 'Categorias de Jogos';
    protected static ?string $navigationGroup = 'Catálogo de Jogos';
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'categorias-de-jogos';

    public ?Category $previewCategory = null;
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
            ->headerActions([
                Tables\Actions\Action::make('create_category')
                    ->label('Nova categoria')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading('Criar categoria de jogos')
                    ->form($this->categoryFormSchema())
                    ->action(function (array $data): void {
                        Category::query()->create($this->normalizePayload($data));
                        $this->clearCategoryCache();

                        Notification::make()
                            ->title('Categoria criada')
                            ->body('A categoria foi cadastrada com sucesso.')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagem')
                    ->getStateUsing(fn (Category $record): ?string => $this->imageUrl($record->image))
                    ->height(58)
                    ->width(94)
                    ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 14px; background:#0a0a0a;']),

                Tables\Columns\TextColumn::make('name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Category $record): string => $record->description ?: 'Sem descrição'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Nome na home / slug')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('games_count')
                    ->label('Jogos')
                    ->state(fn (Category $record): int => $this->gamesCount($record))
                    ->alignCenter()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('url')
                    ->label('Link')
                    ->url(fn (Category $record): ?string => filled($record->url) ? $record->url : null)
                    ->openUrlInNewTab()
                    ->limit(42)
                    ->tooltip(fn (Category $record): ?string => $record->url)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('sem_imagem')
                    ->label('Sem imagem')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('image')->orWhere('image', ''))),

                Tables\Filters\Filter::make('sem_slug')
                    ->label('Sem nome na home')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('slug')->orWhere('slug', ''))),

                Tables\Filters\Filter::make('com_link')
                    ->label('Com link')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('url')->where('url', '!=', '')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Category $record) => $this->openPreview($record)),

                Tables\Actions\Action::make('edit_category')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (Category $record): string => 'Editar categoria: ' . $record->name)
                    ->fillForm(fn (Category $record): array => [
                        'name' => $record->name,
                        'description' => $record->description,
                        'image' => $this->normalizeFileUploadStateForForm($record->image),
                        'slug' => $record->slug,
                        'url' => $record->url,
                    ])
                    ->form($this->categoryFormSchema(isEdit: true))
                    ->action(function (Category $record, array $data): void {
                        $record->update($this->normalizePayload($data, $record));
                        $this->clearCategoryCache($record);

                        Notification::make()
                            ->title('Categoria atualizada')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->before(function (Category $record): void {
                        if ($this->gamesCount($record) > 0) {
                            Notification::make()
                                ->title('Categoria possui jogos vinculados')
                                ->body('Revise os vínculos antes de excluir para evitar perda de organização no catálogo.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->after(fn () => $this->clearCategoryCache()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionadas')
                        ->after(fn () => $this->clearCategoryCache()),
                ]),
            ])
            ->defaultSort('name')
            ->striped();
    }

    private function query(): Builder
    {
        return Category::query();
    }

    private function categoryFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Como o frontend usa a categoria')
                ->description('A API retorna name, description, image, slug e url. O catálogo usa esses dados para montar seções e atalhos de jogos.')
                ->schema([
                    Placeholder::make('frontend_preview')
                        ->label('Prévia do título')
                        ->content(fn (Get $get): HtmlString => new HtmlString(
                            '<div style="display:grid;gap:6px;color:#d4d4d4;font-size:12px">'
                            . '<div><strong style="color:#fff">' . e((string) ($get('name') ?: 'Nome da categoria')) . '</strong></div>'
                            . '<div>' . e((string) ($get('description') ?: 'Descrição curta da categoria')) . '</div>'
                            . '<div><span style="display:inline-flex;padding:4px 9px;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;font-weight:900">' . e((string) ($get('slug') ?: 'sem-slug')) . '</span></div>'
                            . '</div>'
                        )),

                    Placeholder::make('frontend_rules')
                        ->label('Observação')
                        ->content(new HtmlString('
                            <div style="display:grid;gap:6px;color:#d4d4d4;font-size:12px;line-height:1.55">
                                <div><strong style="color:#fff">Nome:</strong> aparece como título da seção.</div>
                                <div><strong style="color:#fff">Slug/Nome na Home:</strong> usado para identificação visual ou rota amigável.</div>
                                <div><strong style="color:#fff">URL:</strong> opcional, usada quando a categoria precisa apontar para uma página específica.</div>
                                <div><strong style="color:#fff">Vínculos:</strong> os jogos são ligados pela tabela <code style="color:#fdba74">category_game</code>.</div><div><strong style="color:#fff">Cache:</strong> ao salvar, os caches de categorias e catálogo são limpos.</div>
                            </div>
                        '))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Informações da categoria')
                ->description('Cadastre categorias para organizar jogos exibidos na plataforma.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome da categoria')
                        ->placeholder('Ex.: Slots Populares')
                        ->required()
                        ->live(onBlur: true)
                        ->unique(table: 'categories', column: 'name', ignoreRecord: $isEdit)
                        ->maxLength(191)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                            if (filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Nome exibido na home / slug')
                        ->placeholder('slots-populares')
                        ->maxLength(191)
                        ->extraInputAttributes([
                            'style' => 'text-transform: lowercase',
                            'autocomplete' => 'off',
                            'spellcheck' => 'false',
                        ])
                        ->helperText('Pode ser gerado pelo nome. Use sem espaços e sem acentos.'),

                    Textarea::make('description')
                        ->label('Descrição')
                        ->placeholder('Descreva brevemente esta categoria')
                        ->rows(3)
                        ->required()
                        ->maxLength(191)
                        ->columnSpanFull(),

                    FileUpload::make('image')
                        ->label('Imagem da categoria')
                        ->image()
                        ->imagePreviewHeight('120')
                        ->helperText('Recomendado: imagem 500x500')
                        ->saveUploadedFileUsing(
                            fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                        )
                        ->columnSpanFull(),

                    TextInput::make('url')
                        ->label('Link opcional')
                        ->placeholder('https://exemplo.com ou /casino?category=slots')
                        ->maxLength(191)
                        ->columnSpanFull()
                        ->helperText('Use apenas se a categoria precisar redirecionar para uma rota/link específico.'),
                ])
                ->columns(2),
        ];
    }

    private function normalizePayload(array $data, ?Category $record = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        return [
            'name' => $name,
            'description' => trim((string) ($data['description'] ?? '')),
            'image' => $this->normalizeUploadedPath($data['image'] ?? null) ?: $record?->image,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($name),
            'url' => filled($data['url'] ?? null) ? trim((string) $data['url']) : null,
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

    public function openPreview(Category $record): void
    {
        $this->previewCategory = $record;
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->previewCategory = null;
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

    public function gamesCount(Category $category): int
    {
        try {
            $pivot = $this->categoryPivotTable();

            if ($pivot !== null) {
                return (int) DB::table($pivot)
                    ->where('category_id', $category->id)
                    ->distinct('game_id')
                    ->count('game_id');
            }

            if (DB::getSchemaBuilder()->hasColumn('games', 'category_id')) {
                return (int) DB::table('games')->where('category_id', $category->id)->count();
            }

            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function stats(): array
    {
        return [
            'total' => Category::query()->count(),
            'without_image' => Category::query()->where(fn (Builder $query) => $query->whereNull('image')->orWhere('image', ''))->count(),
            'without_slug' => Category::query()->where(fn (Builder $query) => $query->whereNull('slug')->orWhere('slug', ''))->count(),
            'with_link' => Category::query()->whereNotNull('url')->where('url', '!=', '')->count(),
            'linked_games' => $this->linkedGamesTotal(),
            'unique_linked_games' => $this->uniqueLinkedGamesTotal(),
            'pivot_table' => $this->categoryPivotTable() ?: 'não encontrada',
            'last_update' => Category::query()->latest('updated_at')->value('updated_at'),
        ];
    }

    public function linkedGamesTotal(): int
    {
        try {
            $pivot = $this->categoryPivotTable();

            if ($pivot !== null) {
                return (int) DB::table($pivot)->count();
            }

            if (DB::getSchemaBuilder()->hasColumn('games', 'category_id')) {
                return (int) DB::table('games')->whereNotNull('category_id')->count();
            }

            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function uniqueLinkedGamesTotal(): int
    {
        try {
            $pivot = $this->categoryPivotTable();

            if ($pivot !== null) {
                return (int) DB::table($pivot)->distinct('game_id')->count('game_id');
            }

            if (DB::getSchemaBuilder()->hasColumn('games', 'category_id')) {
                return (int) DB::table('games')->whereNotNull('category_id')->distinct('id')->count('id');
            }

            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function categoryPivotTable(): ?string
    {
        $schema = DB::getSchemaBuilder();

        if ($schema->hasTable('category_game')) {
            return 'category_game';
        }

        if ($schema->hasTable('category_games')) {
            return 'category_games';
        }

        return null;
    }

    private function clearCategoryCache(?Category $category = null): void
    {
        Cache::forget('pf:v1:categories:list');
        Cache::forget('asset_version');
        Cache::put('asset_version', now()->timestamp, now()->addDays(30));

        if ($category) {
            Cache::forget("pf:v1:categories:show:id:{$category->id}");
            if (filled($category->slug)) {
                Cache::forget("pf:v1:categories:show:slug:{$category->slug}");
            }
        }
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}