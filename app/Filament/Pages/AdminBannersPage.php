<?php

namespace App\Filament\Pages;

use App\Models\Banner;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminBannersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-banners-page';
    protected static ?string $title = 'Banners da Plataforma';
    protected static ?string $navigationLabel = 'Banners da Plataforma';
    protected static ?string $navigationGroup = 'Tema e Aparência';
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'banners-da-plataforma';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function stats(): array
    {
        $base = Banner::query();
        $hasActive = $this->has('is_active');
        $hasDesktop = $this->has('show_desktop');
        $hasMobile = $this->has('show_mobile');

        return [
            'total' => (clone $base)->count(),
            'active' => $hasActive ? (clone $base)->where('is_active', true)->count() : (clone $base)->count(),
            'desktop' => $hasDesktop ? (clone $base)->when($hasActive, fn ($q) => $q->where('is_active', true))->where('show_desktop', true)->count() : (clone $base)->count(),
            'mobile' => $hasMobile ? (clone $base)->when($hasActive, fn ($q) => $q->where('is_active', true))->where('show_mobile', true)->count() : (clone $base)->count(),
            'carousel' => (clone $base)->where('type', 'carousel')->count(),
            'home' => (clone $base)->where('type', 'home')->count(),
            'latest' => (clone $base)->latest('updated_at')->value('updated_at'),
        ];
    }

    public function getPreviewBanners(): Collection
    {
        $query = Banner::query();
        if ($this->has('is_active')) $query->where('is_active', true);
        if ($this->has('sort_order')) $query->orderBy('sort_order');
        return $query->orderByDesc('updated_at')->limit(6)->get();
    }

    public function table(Table $table): Table
    {
        $hasControls = $this->hasDisplayControls();
        $table = $table
            ->deferLoading()
            ->query(Banner::query())
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                ImageColumn::make('image')->label('Imagem')->size(92)->square()->extraImgAttributes(['class' => 'rounded-xl object-cover']),
                TextColumn::make('sort_order')->label('Ordem')->sortable()->alignCenter()->visible(fn () => $this->has('sort_order')),
                TextColumn::make('type')->label('Posição')->badge()->sortable()->formatStateUsing(fn (?string $state): string => $this->typeLabel($state))->color(fn (?string $state): string => $this->typeColor($state)),
                ToggleColumn::make('is_active')->label('Ativo')->visible(fn () => $this->has('is_active')),
                ToggleColumn::make('show_desktop')->label('Desktop')->visible(fn () => $this->has('show_desktop')),
                ToggleColumn::make('show_mobile')->label('Mobile')->visible(fn () => $this->has('show_mobile')),
                TextColumn::make('description')->label('Descrição')->limit(55)->searchable()->placeholder('-'),
                TextColumn::make('link')->label('Link')->limit(36)->copyable()->searchable()->placeholder('-'),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')->label('Buscar')->form([
                    Forms\Components\TextInput::make('value')->label('Descrição, link ou imagem'),
                ])->query(function (Builder $query, array $data): Builder {
                    $search = trim((string) ($data['value'] ?? ''));
                    if ($search === '') return $query;
                    return $query->where(fn (Builder $q) => $q->where('description', 'like', "%{$search}%")->orWhere('link', 'like', "%{$search}%")->orWhere('image', 'like', "%{$search}%"));
                }),
                SelectFilter::make('type')->label('Posição')->options(['carousel' => 'Carrossel', 'home' => 'Página Inicial'])->placeholder('Todas'),
                SelectFilter::make('is_active')->label('Status')->options(['1' => 'Ativos', '0' => 'Inativos'])->visible(fn () => $this->has('is_active')),
                Filter::make('desktop')->label('Desktop')->query(fn (Builder $q): Builder => $q->where('show_desktop', true))->toggle()->visible(fn () => $this->has('show_desktop')),
                Filter::make('mobile')->label('Mobile')->query(fn (Builder $q): Builder => $q->where('show_mobile', true))->toggle()->visible(fn () => $this->has('show_mobile')),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(5)
            ->headerActions([
                Action::make('create_banner')
                    ->label('Novo banner')->icon('heroicon-o-plus')->color('primary')->slideOver()->modalWidth('3xl')
                    ->modalHeading('Cadastrar novo banner')
                    ->form($this->bannerFormSchema())
                    ->action(function (array $data): void {
                        $payload = $this->normalizeBannerPayload($data);
                        if (blank($payload['image'] ?? null)) {
                            Notification::make()->title('Imagem obrigatória')->danger()->send();
                            return;
                        }
                        Banner::query()->create($payload);
                        Notification::make()->title('Banner cadastrado')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('info')->label('Visualizar')->icon('heroicon-o-eye')->color('info')->modalSubmitAction(false)->modalCancelActionLabel('Fechar')->modalWidth('3xl')
                    ->modalContent(fn (Banner $record) => view('filament.pages.partials.banner-info-modal', [
                        'banner' => $record,
                        'typeLabel' => fn ($value) => $this->typeLabel($value),
                        'imageUrl' => fn ($value) => $this->imageUrl($value, $record->updated_at),
                    ])),
                Action::make('edit_banner')->label('Editar')->icon('heroicon-o-pencil-square')->color('warning')->slideOver()->modalWidth('3xl')
                    ->form($this->bannerFormSchema(true))
                    ->fillForm(fn (Banner $record): array => $this->fillBanner($record))
                    ->action(function (Banner $record, array $data): void {
                        $record->update($this->normalizeBannerPayload($data, $record));
                        Notification::make()->title('Banner atualizado')->success()->send();
                    }),
                Action::make('delete')->label('Excluir')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->modalDescription('Se quiser apenas tirar do ar, prefira desativar quando os controles estiverem disponíveis.')
                    ->action(function (Banner $record): void {
                        $record->delete();
                        Notification::make()->title('Banner excluído')->success()->send();
                    }),
            ])
            ->bulkActions($hasControls ? [
                BulkActionGroup::make([
                    BulkAction::make('activate_selected')->label('Ativar selecionados')->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                    BulkAction::make('deactivate_selected')->label('Desativar selecionados')->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                ]),
            ] : [])
            ->emptyStateHeading('Nenhum banner encontrado');

        if ($this->has('sort_order')) {
            $table = $table->defaultSort('sort_order')->reorderable('sort_order');
        } else {
            $table = $table->defaultSort('updated_at', 'desc');
        }

        return $table;
    }

    private function bannerFormSchema(bool $isEdit = false): array
    {
        $fields = [
            Forms\Components\Select::make('type')->label('Posição de exibição')->options(['carousel' => 'Carrossel', 'home' => 'Página Inicial'])->default('home')->required()->native(false),
        ];
        if ($this->has('sort_order')) $fields[] = Forms\Components\TextInput::make('sort_order')->label('Ordem')->numeric()->minValue(0)->default(0)->required();
        if ($this->has('is_active')) $fields[] = Forms\Components\Toggle::make('is_active')->label('Banner ativo')->default(true);
        if ($this->has('show_desktop')) $fields[] = Forms\Components\Toggle::make('show_desktop')->label('Exibir no desktop')->default(true);
        if ($this->has('show_mobile')) $fields[] = Forms\Components\Toggle::make('show_mobile')->label('Exibir no mobile')->default(true);
        $fields[] = Forms\Components\FileUpload::make('image')->label('Imagem do banner')->image()->required(! $isEdit)->helperText('Desktop: 1600x520. Mobile: 1200x520 funciona bem.')->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null)->columnSpanFull();

        return [
            Forms\Components\Section::make('Imagem e exibição')
                ->description($this->hasDisplayControls() ? 'Controle a ordem e os dispositivos.' : 'Modo de compatibilidade: rode as migrations para liberar ordem, status e seleção Desktop/Mobile.')
                ->schema($fields)->columns(3),
            Forms\Components\Section::make('Conteúdo')->schema([
                Forms\Components\TextInput::make('link')->label('Link de destino')->maxLength(191)->columnSpanFull(),
                Forms\Components\Textarea::make('description')->label('Descrição')->rows(3)->columnSpanFull(),
            ]),
        ];
    }

    private function fillBanner(Banner $record): array
    {
        $data = ['type' => $record->type, 'description' => $record->description, 'link' => $record->link, 'image' => $record->image];
        foreach (['is_active','sort_order','show_desktop','show_mobile'] as $field) if ($this->has($field)) $data[$field] = $record->{$field};
        return $data;
    }

    private function normalizeBannerPayload(array $data, ?Banner $record = null): array
    {
        $payload = [
            'type' => $data['type'] ?? ($record?->type ?: 'home'),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'link' => filled($data['link'] ?? null) ? trim((string) $data['link']) : null,
            'image' => $this->extractImagePath($data['image'] ?? null) ?: $record?->image,
        ];
        if ($this->has('is_active')) $payload['is_active'] = (bool) ($data['is_active'] ?? true);
        if ($this->has('sort_order')) $payload['sort_order'] = max(0, (int) ($data['sort_order'] ?? 0));
        if ($this->has('show_desktop')) $payload['show_desktop'] = (bool) ($data['show_desktop'] ?? true);
        if ($this->has('show_mobile')) $payload['show_mobile'] = (bool) ($data['show_mobile'] ?? true);
        return $payload;
    }

    private function extractImagePath(mixed $image): ?string
    {
        if (is_string($image) && filled($image)) return ltrim($image, '/');
        if (is_array($image)) foreach ($image as $item) if ($path = $this->extractImagePath($item)) return $path;
        return null;
    }

    private function has(string $column): bool
    {
        return Schema::hasTable('banners') && Schema::hasColumn('banners', $column);
    }

    private function hasDisplayControls(): bool
    {
        return $this->has('is_active') && $this->has('sort_order') && $this->has('show_desktop') && $this->has('show_mobile');
    }

    public function typeLabel(?string $type): string
    {
        return match ($type) { 'carousel' => 'Carrossel', 'home' => 'Página Inicial', default => $type ?: '-' };
    }

    public function typeColor(?string $type): string
    {
        return match ($type) { 'carousel' => 'info', 'home' => 'success', default => 'gray' };
    }

    public function imageUrl(?string $image, mixed $version = null): ?string
    {
        if (! filled($image)) return null;
        $image = ltrim((string) $image, '/');
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) $url = $image;
        elseif (str_starts_with($image, 'storage/')) $url = asset($image);
        else $url = asset('storage/' . $image);
        if (filled($version)) {
            $ts = $version instanceof \DateTimeInterface ? $version->getTimestamp() : strtotime((string) $version);
            if ($ts) $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $ts;
        }
        return $url;
    }
}
