<?php



namespace App\Filament\Pages;

use App\Models\Banner;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
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
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function stats(): array
    {
        $base = Banner::query();

        return [
            'total' => (clone $base)->count(),
            'carousel' => (clone $base)->where('type', 'carousel')->count(),
            'home' => (clone $base)->where('type', 'home')->count(),
            'with_link' => (clone $base)->whereNotNull('link')->where('link', '!=', '')->count(),
            'without_link' => (clone $base)->where(fn (Builder $query) => $query->whereNull('link')->orWhere('link', ''))->count(),
            'latest' => (clone $base)->latest('updated_at')->value('updated_at'),
        ];
    }

    public function getPreviewBanners(): Collection
    {
        return Banner::query()
            ->latest('updated_at')
            ->limit(6)
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->bannerQuery())
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagem')
                    ->size(92)
                    ->square()
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover',
                    ]),

                TextColumn::make('type')
                    ->label('Posição')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $this->typeLabel($state))
                    ->color(fn (?string $state): string => $this->typeColor($state)),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(70)
                    ->searchable()
                    ->tooltip(fn (Banner $record): ?string => $record->description)
                    ->placeholder('-'),

                TextColumn::make('link')
                    ->label('Link')
                    ->limit(45)
                    ->copyable()
                    ->copyMessage('Link copiado.')
                    ->url(fn (Banner $record): ?string => filled($record->link) ? $record->link : null, true)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (Banner $record): ?string => $record->updated_at?->diffForHumans()),

                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')
                    ->label('Buscar')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Descrição, link ou imagem'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->where('description', 'like', "%{$search}%")
                                ->orWhere('link', 'like', "%{$search}%")
                                ->orWhere('image', 'like', "%{$search}%");
                        });
                    }),

                SelectFilter::make('type')
                    ->label('Posição')
                    ->options([
                        'carousel' => 'Carrossel',
                        'home' => 'Página Inicial',
                    ])
                    ->placeholder('Todas'),

                Filter::make('has_link')
                    ->label('Com link')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('link')->where('link', '!=', ''))
                    ->toggle(),

                Filter::make('without_link')
                    ->label('Sem link')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $subQuery) => $subQuery->whereNull('link')->orWhere('link', '')))
                    ->toggle(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('create_banner')
                    ->label('Novo banner')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading('Cadastrar novo banner')
                    ->modalDescription('Envie a imagem, escolha a posição e configure link/descrição.')
                    ->form($this->bannerFormSchema())
                    ->action(function (array $data): void {
                        $payload = $this->normalizeBannerPayload($data);

                        if (blank($payload['image'] ?? null)) {
                            Notification::make()
                                ->title('Imagem obrigatória')
                                ->body('O upload não retornou o caminho da imagem. Verifique a permissão de storage/app/public/uploads e tente reenviar.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Banner::create($payload);

                        Notification::make()
                            ->title('Banner cadastrado')
                            ->success()
                            ->send();
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
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Banner $record): string => 'Editar banner #' . $record->id)
                    ->form($this->bannerFormSchema(isEdit: true))
                    ->fillForm(fn (Banner $record): array => [
                        'type' => $record->type,
                        'description' => $record->description,
                        'link' => $record->link,
                        'image' => $record->image,
                    ])
                    ->action(function (Banner $record, array $data): void {
                        $record->update($this->normalizeBannerPayload($data, $record));

                        Notification::make()
                            ->title('Banner atualizado')
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir banner?')
                    ->modalDescription('Essa ação remove apenas o registro do banner. O arquivo físico não será apagado automaticamente.')
                    ->modalSubmitActionLabel('Sim, excluir')
                    ->action(function (Banner $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('Banner excluído')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete_selected')
                        ->label('Excluir selecionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Excluir banners selecionados?')
                        ->modalDescription('Remove somente os registros selecionados. Arquivos físicos não serão apagados automaticamente.')
                        ->modalSubmitActionLabel('Sim, excluir selecionados')
                        ->action(function (Collection $records): void {
                            $count = $records->count();

                            $records->each(function (Banner $record): void {
                                $record->delete();
                            });

                            Notification::make()
                                ->title('Banners excluídos')
                                ->body($count . ' registro(s) removido(s).')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Nenhum banner encontrado')
            ->emptyStateDescription('Cadastre banners para carrossel e página inicial.');
    }

    private function bannerQuery(): Builder
    {
        return Banner::query();
    }

    private function bannerFormSchema(bool $isEdit = false): array
    {
        return [
            Forms\Components\Section::make('Imagem e posição')
                ->description('Escolha onde o banner será exibido e envie uma imagem otimizada.')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Posição de exibição')
                        ->options([
                            'carousel' => 'Carrossel',
                            'home' => 'Página Inicial',
                        ])
                        ->required()
                        ->native(false),

                    Forms\Components\FileUpload::make('image')
                        ->label('Imagem do Banner')
                        ->image()
                        ->required(! $isEdit)
                        ->helperText('Recomendado: 1200x445, JPG/PNG/WEBP/GIF até 4MB.')
                        ->saveUploadedFileUsing(
                            fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Conteúdo')
                ->schema([
                    Forms\Components\TextInput::make('link')
                        ->label('Link de destino')
                        ->placeholder('https://seudominio.com/promocao')
                        ->url()
                        ->maxLength(191)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrição')
                        ->placeholder('Descrição interna ou texto de apoio do banner')
                        ->rows(4)
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
            'description' => $data['description'] ?? null,
            'link' => $data['link'] ?? null,
            'image' => $image ?: $record?->image,
        ];
    }

    private function extractImagePath(mixed $image): ?string
    {
        if (is_string($image) && filled($image)) {
            return ltrim($image, '/');
        }

        if (is_array($image)) {
            foreach ($image as $item) {
                $path = $this->extractImagePath($item);

                if (filled($path)) {
                    return ltrim($path, '/');
                }
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
        if (! filled($image)) {
            return null;
        }

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

            if ($ts) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $ts;
            }
        }

        return $url;
    }
}