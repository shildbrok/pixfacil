<?php

namespace App\Filament\Pages;

use App\Models\HouseGame;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminRetroGamesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-retro-games-page';
    protected static ?string $title = 'Jogos Retrô';
    protected static ?string $navigationLabel = 'Jogos Retrô';
    protected static ?string $navigationIcon = 'heroicon-o-command-line';
    protected static ?string $slug = 'jogos-retro';

    private const AUDIT_FIELDS = [
        'name',
        'description',
        'cover',
        'icon',
        'active',
        'show_home',
        'sort_order',
        'min_bet',
        'max_bet',
        'coin_rate',
        'meta_multiplier',
        'max_win_multiplier',
        'player_speed',
        'engine_params',
        'min_win_seconds',
        'round_timeout_seconds',
    ];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getStats(): array
    {
        return [
            'total' => HouseGame::count(),
            'active' => HouseGame::where('active', true)->count(),
            'home' => HouseGame::where('show_home', true)->count(),
            'views' => (int) HouseGame::sum('views'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(HouseGame::query())
            ->deferLoading()
            ->columns([
                ImageColumn::make('cover')->label('Capa')
                    ->getStateUsing(fn (HouseGame $record): ?string => $this->imageUrl($record->cover))
                    ->height(58)->width(96)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:12px;background:#050505;']),
                Tables\Columns\TextColumn::make('name')->label('Jogo')->weight('bold')->searchable()->sortable()
                    ->description(fn (HouseGame $record): string => $record->slug),
                Tables\Columns\IconColumn::make('active')->label('Ativo')->boolean(),
                Tables\Columns\IconColumn::make('show_home')->label('Home')->boolean(),
                Tables\Columns\TextColumn::make('min_bet')->label('Aposta')
                    ->formatStateUsing(fn ($state, HouseGame $record): string => 'R$ '.number_format((float) $state, 2, ',', '.').' – R$ '.number_format((float) $record->max_bet, 2, ',', '.')),
                Tables\Columns\TextColumn::make('meta_multiplier')->label('Meta')->suffix('×')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('max_win_multiplier')->label('Teto')->suffix('×')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('views')->label('Acessos')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('active')->label('Status')->options(['1' => 'Ativos', '0' => 'Inativos'])
                    ->query(fn (Builder $q, array $data): Builder => isset($data['value']) && $data['value'] !== null && $data['value'] !== '' ? $q->where('active', (bool) $data['value']) : $q),
                Tables\Filters\SelectFilter::make('show_home')->label('Home')->options(['1' => 'Exibidos', '0' => 'Ocultos'])
                    ->query(fn (Builder $q, array $data): Builder => isset($data['value']) && $data['value'] !== null && $data['value'] !== '' ? $q->where('show_home', (bool) $data['value']) : $q),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_retro')
                    ->label('Configurar')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->modalHeading(fn (HouseGame $record): string => 'Configurar jogo retrô: '.$record->name)
                    ->fillForm(fn (HouseGame $record): array => [
                        'slug' => $record->slug,
                        'name' => $record->name,
                        'description' => $record->description,
                        'active' => $record->active,
                        'show_home' => $record->show_home,
                        'sort_order' => $record->sort_order,
                        'min_bet' => (float) $record->min_bet,
                        'max_bet' => (float) $record->max_bet,
                        'coin_rate' => (float) $record->coin_rate,
                        'meta_multiplier' => (float) $record->meta_multiplier,
                        'max_win_multiplier' => (float) $record->max_win_multiplier,
                        'player_speed' => (float) $record->player_speed,
                        'min_win_seconds' => $record->min_win_seconds,
                        'round_timeout_seconds' => $record->round_timeout_seconds,
                        'engine_params' => $record->engine_params ?? [],
                        'admin_password' => null,
                    ])
                    ->form($this->formSchema())
                    ->action(function (HouseGame $record, array $data): void {
                        if (! app(AdminActionGuard::class)->confirm((string) ($data['admin_password'] ?? ''))) {
                            Notification::make()
                                ->title('Acesso negado')
                                ->body('PIN administrativo inválido.')
                                ->danger()
                                ->send();
                            return;
                        }

                        unset($data['admin_password']);
                        $before = $record->only(self::AUDIT_FIELDS);

                        $cover = $this->normalizeUpload($data['cover_upload'] ?? null);
                        $icon = $this->normalizeUpload($data['icon_upload'] ?? null);
                        unset($data['cover_upload'], $data['icon_upload'], $data['slug']);
                        if ($cover) $data['cover'] = $cover;
                        if ($icon) $data['icon'] = $icon;

                        $record->update($data);
                        $fresh = $record->fresh();

                        AdminAudit::log(
                            'retro_game.config.update',
                            $fresh,
                            $before,
                            $fresh->only(self::AUDIT_FIELDS),
                            'Atualizou configuração e economia de jogo retrô.'
                        );

                        Notification::make()->title('Jogo retrô atualizado')->success()->send();
                    }),
            ])
            ->defaultSort('sort_order')
            ->striped();
    }

    private function formSchema(): array
    {
        return [
            Forms\Components\Hidden::make('slug'),
            Forms\Components\Section::make('Exibição')
                ->description('Esses campos controlam apenas o catálogo retrô. Não alteram jogos PlayFiver.')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(191),
                    Forms\Components\TextInput::make('sort_order')->label('Ordem')->numeric()->minValue(0)->required(),
                    Forms\Components\Toggle::make('active')->label('Ativo'),
                    Forms\Components\Toggle::make('show_home')->label('Exibir na Home'),
                    Forms\Components\Textarea::make('description')->label('Descrição')->rows(3)->columnSpanFull(),
                    Forms\Components\FileUpload::make('cover_upload')->label('Nova capa (opcional)')->image()->imageEditor()
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                    Forms\Components\FileUpload::make('icon_upload')->label('Novo ícone (opcional)')->image()->imageEditor()
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null),
                ])->columns(4),

            Forms\Components\Section::make('Economia da rodada')
                ->description('A aposta e o pagamento ficam no módulo retrô isolado; o saldo usa o mesmo serviço de carteira/rollover do cassino.')
                ->schema([
                    Forms\Components\TextInput::make('min_bet')->label('Aposta mínima')->prefix('R$')->numeric()->minValue(0.01)->step(0.01)->required(),
                    Forms\Components\TextInput::make('max_bet')->label('Aposta máxima')->prefix('R$')->numeric()->minValue(0.01)->step(0.01)->required(),
                    Forms\Components\TextInput::make('meta_multiplier')->label('Meta')->suffix('× aposta')->numeric()->minValue(0)->step(0.0001)->required(),
                    Forms\Components\TextInput::make('max_win_multiplier')->label('Teto de pagamento')->suffix('× aposta')->numeric()->minValue(0)->step(0.0001)->required(),
                    Forms\Components\TextInput::make('coin_rate')->label('R$/ponto')->prefix('R$')->numeric()->minValue(0)->step(0.000001)->required(),
                    Forms\Components\TextInput::make('player_speed')->label('Velocidade base')->numeric()->minValue(0)->step(0.0001)->required(),
                ])->columns(3),

            Forms\Components\Section::make('Proteções da rodada')
                ->schema([
                    Forms\Components\TextInput::make('min_win_seconds')->label('Tempo mínimo para vitória')->suffix('s')->numeric()->minValue(0)->maxValue(3600)->required()
                        ->helperText('Bloqueia liquidação de vitória antes desse tempo.'),
                    Forms\Components\TextInput::make('round_timeout_seconds')->label('Expiração da rodada')->suffix('s')->numeric()->minValue(60)->maxValue(86400)->required(),
                ])->columns(2),

            Forms\Components\Section::make('Subway Money')
                ->visible(fn (Get $get): bool => $get('slug') === 'sub')
                ->schema([
                    Forms\Components\Placeholder::make('sub_help')->label('Motor')->content('Usa coin_rate, meta e velocidade base definidos acima.'),
                ]),

            Forms\Components\Section::make('Angry Cash')
                ->visible(fn (Get $get): bool => $get('slug') === 'angry')
                ->schema([
                    Forms\Components\TextInput::make('engine_params.game_difficulty')->label('Dificuldade')->numeric()->minValue(0)->step(0.1),
                ]),

            Forms\Components\Section::make('Jetpack Cash')
                ->visible(fn (Get $get): bool => $get('slug') === 'jetpack')
                ->schema([
                    Forms\Components\TextInput::make('engine_params.missile_speed')->label('Velocidade dos mísseis')->numeric()->minValue(1),
                ]),

            Forms\Components\Section::make('Candy Cash')
                ->visible(fn (Get $get): bool => $get('slug') === 'candy')
                ->schema([
                    Forms\Components\TextInput::make('engine_params.timer')->label('Tempo de jogo')->suffix('s')->numeric()->minValue(1),
                ]),

            Forms\Components\Section::make('Pacman Cash')
                ->visible(fn (Get $get): bool => $get('slug') === 'pacman')
                ->schema([
                    Forms\Components\TextInput::make('engine_params.lives')->label('Vidas')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('engine_params.ghost_points')->label('Valor por fantasma')->prefix('R$')->numeric()->minValue(0)->step(0.01),
                ])->columns(2),

            Forms\Components\Section::make('Fruit Ninja')
                ->visible(fn (Get $get): bool => $get('slug') === 'fruit')
                ->schema([
                    Forms\Components\TextInput::make('engine_params.fruit_rate')->label('Taxa de frutas')->numeric()->minValue(0)->step(0.01),
                    Forms\Components\TextInput::make('engine_params.drop_duration')->label('Duração da queda')->suffix('ms')->numeric()->minValue(1),
                ])->columns(2),

            Forms\Components\Section::make('Helix Cash')
                ->visible(fn (Get $get): bool => $get('slug') === 'helix')
                ->schema([
                    Forms\Components\TextInput::make('engine_params.gravity')->label('Gravidade')->numeric()->step(0.0001),
                    Forms\Components\TextInput::make('engine_params.open_percent')->label('Área aberta')->numeric()->step(0.01),
                    Forms\Components\TextInput::make('engine_params.danger_percent')->label('Área de perigo')->numeric()->step(0.01),
                    Forms\Components\TextInput::make('engine_params.base_earn')->label('Ganho base')->prefix('R$')->numeric()->step(0.01),
                    Forms\Components\TextInput::make('engine_params.speed_increase')->label('Aumento de velocidade')->numeric()->step(0.0001),
                ])->columns(3),

            Forms\Components\Section::make('Block Win')
                ->visible(fn (Get $get): bool => $get('slug') === 'blockwin')
                ->schema([
                    Forms\Components\Select::make('engine_params.difficulty')->label('Dificuldade')->options(['easy' => 'Fácil', 'normal' => 'Normal', 'hard' => 'Difícil']),
                    Forms\Components\TextInput::make('engine_params.score_multiplier')->label('Multiplicador de pontos')->numeric()->minValue(0)->step(0.1),
                    Forms\Components\TextInput::make('engine_params.easy_start_moves')->label('Jogadas fáceis iniciais')->numeric()->minValue(0),
                ])->columns(3),

            Forms\Components\Section::make('Parâmetros avançados')
                ->collapsed()
                ->schema([
                    Forms\Components\KeyValue::make('engine_params')->label('Configuração completa do engine')->keyLabel('Parâmetro')->valueLabel('Valor')->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Confirmação administrativa')
                ->description('Alterações neste jogo afetam diretamente a economia das rodadas.')
                ->schema([
                    Forms\Components\TextInput::make('admin_password')
                        ->label('PIN administrativo')
                        ->password()
                        ->numeric()
                        ->length(6)
                        ->required(),
                ]),
        ];
    }

    private function normalizeUpload(mixed $value): ?string
    {
        if (is_array($value)) $value = reset($value) ?: null;
        if (! is_string($value) || trim($value) === '') return null;
        return ltrim(trim($value), '/');
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'retro-games/')) return asset($path);
        if (str_starts_with($path, 'storage/')) return asset($path);
        return asset('storage/' . $path);
    }
}
