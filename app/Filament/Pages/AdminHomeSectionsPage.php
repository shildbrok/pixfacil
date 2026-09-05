<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Game;
use App\Models\HomeSection;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class AdminHomeSectionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-home-sections-page';

    protected static ?string $title = 'Seções da Home';
    protected static ?string $navigationLabel = 'Seções da Home';
    protected static ?string $navigationGroup = 'Catálogo de Jogos';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'secoes-da-home';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function getStats(): array
    {
        return [
            'total'  => HomeSection::count(),
            'active' => HomeSection::where('active', true)->count(),
        ];
    }

    private function typeOptions(): array
    {
        return [
            'featured' => 'Em destaque (marcados como destaque)',
            'popular'  => 'Populares (mais jogados)',
            'new'      => 'Lançamentos (mais recentes)',
            'recent'   => 'Recentes do jogador',
            'category' => 'Categoria (jogos de uma categoria)',
            'manual'   => 'Manual (você escolhe os jogos)',
        ];
    }

    private function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label('Título da seção')
                ->required()->maxLength(191)
                ->placeholder('Ex: Em Destaque'),

            Forms\Components\TextInput::make('subtitle')
                ->label('Subtítulo (abaixo do título)')
                ->maxLength(191)
                ->placeholder('Ex: Mais jogados (vazio = não mostra)'),

            Forms\Components\Select::make('type')
                ->label('Tipo (como escolhe os jogos)')
                ->options($this->typeOptions())
                ->required()->reactive()->default('category'),

            Forms\Components\Select::make('category_id')
                ->label('Categoria')
                ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => $get('type') === 'category')
                ->required(fn (Get $get) => $get('type') === 'category'),

            Forms\Components\Select::make('manual_games')
                ->label('Jogos escolhidos')
                ->multiple()->searchable()
                ->options(fn () => Game::query()
                    ->whereHas('provider', fn ($q) => $q->where('distribution', 'play_fiver'))
                    ->orderBy('game_name')
                    ->limit(50)
                    ->pluck('game_name', 'id'))
                ->getSearchResultsUsing(fn (string $search) => Game::query()
                    ->whereHas('provider', fn ($q) => $q->where('distribution', 'play_fiver'))
                    ->where('game_name', 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('game_name', 'id'))
                ->getOptionLabelsUsing(fn (array $values) => Game::whereIn('id', $values)->pluck('game_name', 'id')->toArray())
                ->visible(fn (Get $get) => $get('type') === 'manual')
                ->helperText('Digite para buscar. A ordem escolhida é a ordem na home.'),

            Forms\Components\TextInput::make('games_limit')
                ->label('Quantidade de jogos')
                ->numeric()->minValue(1)->maxValue(50)->default(12)->required(),

            Forms\Components\TextInput::make('sort_order')
                ->label('Ordem na home (menor = primeiro)')
                ->numeric()->minValue(0)->default(0)->required(),

            Forms\Components\Toggle::make('active')
                ->label('Ativa (aparece na home)')
                ->default(true),
        ];
    }

    private function persist(HomeSection $section, array $data): void
    {
        $manual = $data['manual_games'] ?? [];

        if (($data['type'] ?? null) === 'manual' && ! empty($manual)) {
            $sync = [];
            foreach (array_values($manual) as $i => $gameId) {
                $sync[$gameId] = ['sort_order' => $i];
            }
            $section->games()->sync($sync);
        } else {
            $section->games()->detach();
        }

        Cache::flush();
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(HomeSection::query())
            ->headerActions([
                Tables\Actions\Action::make('create_section')
                    ->label('Nova seção')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Criar seção da home')
                    ->form($this->formSchema())
                    ->action(function (array $data): void {
                        $manual = $data['manual_games'] ?? [];
                        unset($data['manual_games']);
                        $section = HomeSection::create($data);
                        $this->persist($section, array_merge($data, ['manual_games' => $manual]));

                        Notification::make()->title('Seção criada')->success()->send();
                    }),
            ])
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable()->alignCenter(),
                TextColumn::make('title')->label('Título')->weight('bold')->searchable()
                    ->description(fn (HomeSection $r): ?string => $r->subtitle ?: null),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->formatStateUsing(fn (string $state): string => [
                        'featured' => 'Destaque', 'popular' => 'Populares', 'new' => 'Lançamentos',
                        'recent' => 'Recentes', 'category' => 'Categoria', 'manual' => 'Manual',
                    ][$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'featured' => 'warning', 'popular' => 'success', 'new' => 'info',
                        'recent' => 'primary', 'manual' => 'danger', default => 'gray',
                    }),
                TextColumn::make('category.name')->label('Categoria')->placeholder('—'),
                TextColumn::make('games_limit')->label('Qtd')->alignCenter(),
                IconColumn::make('active')->label('Ativa')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options($this->typeOptions()),
                Tables\Filters\Filter::make('active')->label('Só ativas')
                    ->query(fn (Builder $q): Builder => $q->where('active', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_section')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (HomeSection $r): string => 'Editar: ' . $r->title)
                    ->fillForm(fn (HomeSection $record): array => array_merge(
                        $record->only(['title', 'subtitle', 'type', 'category_id', 'games_limit', 'sort_order', 'active']),
                        ['manual_games' => $record->games()->orderBy('home_section_game.sort_order')->pluck('games.id')->toArray()]
                    ))
                    ->form($this->formSchema())
                    ->action(function (HomeSection $record, array $data): void {
                        $manual = $data['manual_games'] ?? [];
                        unset($data['manual_games']);
                        $record->update($data);
                        $this->persist($record, array_merge($data, ['manual_games' => $manual]));

                        Notification::make()->title('Seção atualizada')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->after(fn (HomeSection $record) => $record->games()->detach()),
            ])
            ->defaultSort('sort_order')
            ->striped();
    }
}
