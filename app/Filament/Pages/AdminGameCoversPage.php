<?php

namespace App\Filament\Pages;

use App\Models\Game;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminGameCoversPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-game-covers-page';
    protected static ?string $title = 'Capas de Vitrine';
    protected static ?string $navigationLabel = 'Capas de Vitrine';
    protected static ?string $navigationGroup = 'Catálogo de Jogos';
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $slug = 'capas-de-vitrine';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->deferLoading()
            ->columns([
                ImageColumn::make('pixfacil_home_cover')
                    ->label('Vitrine PixFácil')
                    ->getStateUsing(fn (Game $record): ?string => $this->imageUrl($record->pixfacil_home_cover ?: $record->cover))
                    ->height(76)
                    ->width(128)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:12px;background:#050805']),

                ImageColumn::make('cover')
                    ->label('Original')
                    ->getStateUsing(fn (Game $record): ?string => $this->imageUrl($record->cover))
                    ->height(58)
                    ->width(92)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:10px;background:#050805']),

                Tables\Columns\TextColumn::make('game_name')
                    ->label('Jogo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Game $record): string => ($record->provider?->name ?: 'Sem provedor') . ' • ' . ($record->pixfacil_home_cover ? 'capa personalizada' : 'usando original')),

                Tables\Columns\IconColumn::make('show_home')
                    ->label('Home')
                    ->boolean(),

                Tables\Columns\IconColumn::make('pixfacil_home_cover')
                    ->label('Personalizada')
                    ->boolean(fn (?string $state): bool => filled($state)),
            ])
            ->filters([
                Tables\Filters\Filter::make('home')
                    ->label('Somente jogos da Home')
                    ->query(fn (Builder $query): Builder => $query->where('show_home', 1))
                    ->default(),

                Tables\Filters\Filter::make('sem_capa_personalizada')
                    ->label('Sem capa PixFácil')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('pixfacil_home_cover')->orWhere('pixfacil_home_cover', ''))),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_showcase')
                    ->label('Trocar capa')
                    ->icon('heroicon-o-photo')
                    ->color('success')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Game $record): string => 'Capa de vitrine: ' . $record->game_name)
                    ->fillForm(fn (Game $record): array => [
                        'pixfacil_home_cover' => $this->uploadState($record->pixfacil_home_cover),
                    ])
                    ->form([
                        FileUpload::make('pixfacil_home_cover')
                            ->label('Capa PixFácil')
                            ->image()
                            ->imageEditor()
                            ->helperText('Recomendado: 960×540 (16:9), WEBP/JPG/PNG até 4 MB. Essa imagem é usada na Home do PC e mobile. A capa original do provedor não é alterada.')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                $uploaded = \Helper::upload($file);
                                if (! is_array($uploaded) || blank($uploaded['path'] ?? null)) {
                                    Notification::make()->danger()->title('Falha no upload')->body('Use JPG, PNG ou WEBP de até 4 MB.')->persistent()->send();
                                    throw new Halt();
                                }
                                return $uploaded['path'];
                            }),
                    ])
                    ->action(function (Game $record, array $data): void {
                        $path = $this->normalizeUploadedPath($data['pixfacil_home_cover'] ?? null);
                        $record->update(['pixfacil_home_cover' => $path]);
                        $this->flushCaches();
                        Notification::make()->title('Capa de vitrine atualizada')->success()->send();
                    }),

                Tables\Actions\Action::make('restore_original')
                    ->label('Usar original')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Game $record): bool => filled($record->pixfacil_home_cover))
                    ->requiresConfirmation()
                    ->action(function (Game $record): void {
                        $record->update(['pixfacil_home_cover' => null]);
                        $this->flushCaches();
                        Notification::make()->title('Capa original restaurada')->success()->send();
                    }),
            ])
            ->defaultSort('views', 'desc');
    }

    private function query(): Builder
    {
        if (! Schema::hasColumn('games', 'pixfacil_home_cover')) {
            return Game::query()->whereRaw('1 = 0');
        }

        return Game::query()->with('provider');
    }

    public function migrationReady(): bool
    {
        return Schema::hasTable('games') && Schema::hasColumn('games', 'pixfacil_home_cover');
    }

    public function imageUrl(?string $image): ?string
    {
        if (! filled($image)) return null;
        $image = ltrim((string) $image, '/');
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) return $image;
        if (str_starts_with($image, 'storage/')) return asset($image);
        if (str_starts_with($image, 'uploads/')) return asset('storage/' . $image);
        return asset('storage/' . $image);
    }

    private function uploadState(?string $value): ?array
    {
        return filled($value) ? [$value] : null;
    }

    private function normalizeUploadedPath(mixed $value): ?string
    {
        if (blank($value)) return null;
        if (is_string($value)) return ltrim($value, '/');
        if (is_array($value)) return $this->normalizeUploadedPath(reset($value));
        return null;
    }

    private function flushCaches(): void
    {
        Game::clearCatalogCaches();
        Cache::forget('admin:games:stats:v1');
        Cache::put('asset_version', now()->timestamp, now()->addDays(30));
    }
}
