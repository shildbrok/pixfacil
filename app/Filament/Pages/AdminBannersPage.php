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

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'desktop' => (clone $base)->where('is_active', true)->where('show_desktop', true)->count(),
            'mobile' => (clone $base)->where('is_active', true)->where('show_mobile', true)->count(),
            'carousel' => (clone $base)->where('type', 'carousel')->count(),
            'home' => (clone $base)->where('type', 'home')->count(),
            'latest' => (clone $base)->latest('updated_at')->value('updated_at'),
        ];
    }

    public function getPreviewBanners(): Collection
    {
        return Banner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(Banner::query())
            ->defaultSort('sort_order')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagem')
                    ->size(92)
                    ->square()
                    ->extraImgAttributes(['class' => 'rounded-xl object-cover']),

                TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('type')
                    ->label('Posição')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $this->typeLabel($state))
                    ->color(fn (?string $state): string => $this->typeColor($state)),

                ToggleColumn::make('is_active')->label('Ativo'),
                ToggleColumn::make('show_desktop')->label('Desktop'),
                ToggleColumn::make('show_mobile')->label('Mobile'),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(55)
                    ->searchable()
                    ->tooltip(fn (Banner $record): ?string => $record->description)
                    ->placeholder('-'),

                TextColumn::make('link')
                    ->label('Link')
                    ->limit(36)
                    ->copyable()
                    ->copyMessage('Link copiado.')
                    ->searchable()
                    ->placeholder('-'),

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
                        Forms\Components\TextInput::make('value')->label('Descrição, link ou imagem'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));
                        if ($search === '') return $query;

                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->where('description', 'like', "%{$search}%")
                                ->orWhere('link', 'like', "%{$search}%")
                                ->orWhere('image', 'like', "%{$search}%");
                        });
                    }),

                SelectFilter::make('type')
                    ->label('Posição')
                    ->options(['carousel' => 'Carrossel', 'home' => 'Página Inicial'])
                    ->placeholder('Todas'),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options(['1' => 'Ativos', '0' => 'Inativos']),

                Filter::make('desktop')->label('Desktop')->query(fn (Builder $query): Builder => $query->where('show_desktop', true))->toggle(),
                Filter::make('mobile')->label('Mobile')->query(fn (Builder $query): Builder => $query->where('show_mobile', true))->toggle(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(5)
            ->headerActions([
                Action::make('create_banner')
                    ->label('Novo banner')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading('Cadastrar novo banner')
                    ->modalDescription('Envie a arte, defina a ordem e escolha em quais dispositivos ela aparece.')
                    ->form($this->bannerFormSchema())
                    ->action(function (array $data): void {
                        $payload = $this->normalizeBannerPayload($data);

                        if (blank($payload['image'] ?? null)) {
                            Notification::make()
                                ->title('Imagem obrigatória')
                                ->body('O upload não retornou o caminho da imagem.')
                                ->danger()
                                ->send();
                            return;
                        }

                        Banner::query()->create($payload);
                        Notification::make()->title('Banner cadastrado')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('info')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Banner $record): string => 'Banner #' . $record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('3xl')
                    ->modalContent(fn (Banner $record) => view('filament.pages.partials.banner-info-modal', [
                        'banner' => $record,
                        'typeLabel' => fn ($value) => $this->typeLabel($value),
                        'imageUrl' => fn ($value) => $this->imageUrl($value, $record->updated_at),
                    ])),

                Action::make('edit_banner')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (Banner $record): string => 'Editar banner #' . $record->id)
                    ->form($this->bannerFormSchema(isEdit: true))
                    ->fillForm(fn (Banner $record): array => [
                        'type' => $record->type,
                        'description' => $record->description,
                        'link' => $record->link,
                        'image' => $record->image,
                        'is_active' => $record->is_active,
                        'sort_order' => $record->sort_order,
                        'show_desktop' => $record->show_desktop,
                        'show_mobile' => $record->show_mobile,
                    ])
                    ->action(function (Banner $record, array $data): void {
                        $record->update($this->normalizeBannerPayload($data, $record));
                        Notification::make()->title('Banner atualizado')->success()->send();
                    }),

                Action::make('delete')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir banner?')
                    ->modalDescription('Se quiser apenas tirar do ar, prefira desativar o banner.')
                    ->modalSubmitActionLabel('Sim, excluir')
                    ->action(function (Banner $record): void {
                        $record->delete();
                        Notification::make()->title('Banner excluído')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('activate_selected')
                        ->label('Ativar selecionados')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate_selected')
                        ->label('Desativar selecionados')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Nenhum banner encontrado')
            ->emptyStateDescription('Cadastre banners e controle desktop/mobile direto pelo Admin.');
    }

    private function bannerFormSchema(bool $isEdit = false): array
    {
        return [
            Forms\Components\Section::make('Imagem e exibição')
                ->description('O mesmo cadastro pode alimentar o desktop e o mobile sem alterar código.')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Posição de exibição')
                        ->options(['carousel' => 'Carrossel', 'home' => 'Página Inicial'])
                        ->default('home')
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordem')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')->label('Banner ativo')->default(true),
                    Forms\Components\Toggle::make('show_desktop')->label('Exibir no desktop')->default(true),
                    Forms\Components\Toggle::make('show_mobile')->label('Exibir no mobile')->default(true),

                    Forms\Components\FileUpload::make('image')
                        ->label('Imagem do banner')
                        ->image()
                        ->required(! $isEdit)
                        ->helperText('Desktop: prefira 1600x520 ou proporção próxima de 3:1. Mobile: 1200x520 funciona bem.')
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null)
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Conteúdo')
                ->schema([
                    Forms\Components\TextInput::make('link')
                        ->label('Link de destino')
                        ->placeholder('/bonus ou https://seudominio.com/promocao')
                        ->maxLength(191)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrição')
                        ->placeholder('Texto interno para identificar a campanha no Admin')
                        ->rows(3)
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),
        ];
    }

    private function normalizeBannerPayload(array $data, ?Banner $record = null): array
    {
        $image = $this->extractImagePath($data['image'] ?? null);

        return [
            'type' => $data['type'] ?? ($record?->type ?: 'home'),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'link' => filled($data['link'] ?? null) ? trim((string) $data['link']) : null,
            'image' => $image ?: $record?->image,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'show_desktop' => (bool) ($data['show_desktop'] ?? true),
            'show_mobile' => (bool) ($data['show_mobile'] ?? true),
        ];
    }

    private function extractImagePath(mixed $image): ?string
    {
        if (is_string($image) && filled($image)) return ltrim($image, '/');

        if (is_array($image)) {
            foreach ($image as $item) {
                $path = $this->extractImagePath($item);
                if (filled($path)) return ltrim($path, '/');
            }
        }

        return null;
    }

    public function typeLabel(?string $type): string
    {
        return match ($type) {
            'carousel' => 'Carrossel',
            'home' => 'Página Inicial',
            default => $type ?: '-',
        };
    }

    public function typeColor(?string $type): string
    {
        return match ($type) {
            'carousel' => 'info',
            'home' => 'success',
            default => 'gray',
        };
    }

    public function imageUrl(?string $image, mixed $version = null): ?string
    {
        if (! filled($image)) return null;

        $image = ltrim((string) $image, '/');
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            $url = $image;
        } elseif (str_starts_with($image, 'storage/')) {
            $url = asset($image);
        } else {
            $url = asset('storage/' . $image);
        }

        if (filled($version)) {
            $ts = $version instanceof \DateTimeInterface
                ? $version->getTimestamp()
                : (is_numeric($version) ? (int) $version : strtotime((string) $version));
            if ($ts) $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $ts;
        }

        return $url;
    }
}
