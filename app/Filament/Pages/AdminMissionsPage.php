<?php



namespace App\Filament\Pages;

use App\Models\Mission;
use App\Models\MissionUser;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminMissionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-missions-page';

    protected static ?string $title = 'Missões e Desafios';
    protected static ?string $navigationLabel = 'Missões e Desafios';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'missoes-e-desafios';

    public ?Mission $previewMission = null;
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
                Tables\Actions\Action::make('create_mission')
                    ->label('Nova missão')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading('Criar missão')
                    ->form($this->missionFormSchema())
                    ->action(function (array $data): void {
                        Mission::query()->create($this->normalizePayload($data));
                        $this->clearMissionCache();

                        Notification::make()
                            ->title('Missão criada')
                            ->body('A missão foi cadastrada com sucesso.')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagem')
                    ->getStateUsing(fn (Mission $record): ?string => $this->imageUrl($record->image))
                    ->height(64)
                    ->width(64)
                    ->extraImgAttributes(['style' => 'object-fit: contain; border-radius: 14px; background:#0a0a0a;']),

                Tables\Columns\TextColumn::make('title')
                    ->label('Missão')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Mission $record): string => $record->description ? mb_strimwidth(strip_tags($record->description), 0, 72, '...') : 'Sem descrição'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $this->missionTypeLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'deposit' => 'success',
                        'rounds_played' => 'info',
                        'win_amount' => 'warning',
                        'loss_amount' => 'danger',
                        'game_bet', 'total_bet' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_amount')
                    ->label('Meta')
                    ->formatStateUsing(fn ($state, Mission $record): string => $this->formatTarget($record))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reward')
                    ->label('Recompensa')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('game_id')
                    ->label('Jogo')
                    ->state(fn (Mission $record): string => $this->gameName($record->game_id))
                    ->placeholder('Todos')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'Ativa' : 'Inativa')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativas',
                        'inactive' => 'Inativas',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options($this->missionTypeOptions()),

                Tables\Filters\Filter::make('with_game')
                    ->label('Com jogo vinculado')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('game_id')->where('game_id', '!=', '')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Mission $record) => $this->openPreview($record)),

                Tables\Actions\Action::make('edit_mission')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (Mission $record): string => 'Editar missão: ' . $record->title)
                    ->fillForm(fn (Mission $record): array => [
                        'title' => $record->title,
                        'description' => $record->description,
                        'type' => $record->type,
                        'game_id' => $record->game_id,
                        'target_amount' => $record->target_amount,
                        'reward' => $record->reward,
                        'image' => $this->normalizeFileUploadStateForForm($record->image),
                        'status' => $record->status,
                    ])
                    ->form($this->missionFormSchema(isEdit: true))
                    ->action(function (Mission $record, array $data): void {
                        $record->update($this->normalizePayload($data, $record));
                        $this->clearMissionCache();

                        Notification::make()
                            ->title('Missão atualizada')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Mission $record): string => $record->status === 'active' ? 'Desativar' : 'Ativar')
                    ->icon(fn (Mission $record): string => $record->status === 'active' ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (Mission $record): string => $record->status === 'active' ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Mission $record): void {
                        $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active',
                        ]);
                        $this->clearMissionCache();

                        Notification::make()
                            ->title('Status alterado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->after(fn () => $this->clearMissionCache()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionadas')
                        ->after(fn () => $this->clearMissionCache()),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function query(): Builder
    {
        return Mission::query();
    }

    private function missionFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Como o frontend exibe a missão')
                ->description('A página Vue lê título, descrição, tipo, meta, recompensa e imagem. O progresso é diário e calculado pelo backend.')
                ->schema([
                    Placeholder::make('vue_label')
                        ->label('Etiqueta no card Vue')
                        ->content(fn (Get $get): HtmlString => new HtmlString(
                            '<span style="display:inline-flex;padding:6px 10px;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;font-weight:900;font-size:12px">'
                            . e($this->missionTypeLabel((string) $get('type')))
                            . '</span>'
                        )),

                    Placeholder::make('vue_description')
                        ->label('Descrição curta automática')
                        ->content(fn (Get $get): string => $this->shortDescriptionForType(
                            (string) $get('type'),
                            (float) ($get('target_amount') ?: 0)
                        )),

                    Placeholder::make('mission_help')
                        ->label('Resumo dos tipos')
                        ->content(new HtmlString('
                            <div style="display:grid;gap:6px;color:#d4d4d4;font-size:12px;line-height:1.55">
                                <div><strong style="color:#fff">Depósito:</strong> soma depósitos aprovados do dia.</div>
                                <div><strong style="color:#fff">Aposta total:</strong> soma apostas/rodadas do dia em todos os jogos.</div>
                                <div><strong style="color:#fff">Jogo específico:</strong> exige jogo vinculado.</div>
                                <div><strong style="color:#fff">Rodadas:</strong> meta em quantidade de spins/rounds no jogo vinculado.</div>
                                <div><strong style="color:#fff">Ganho/Perda:</strong> calcula win/loss no jogo vinculado.</div>
                            </div>
                        '))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Informações da missão')
                ->description('Configure o card, objetivo e recompensa da missão.')
                ->schema([
                    TextInput::make('title')
                        ->label('Título da missão')
                        ->placeholder('Ex.: Deposite R$ 50 e ganhe bônus')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Descrição')
                        ->placeholder('Explique de forma simples o que o usuário precisa fazer.')
                        ->rows(3)
                        ->columnSpanFull(),

                    Select::make('type')
                        ->label('Tipo de missão')
                        ->options($this->missionTypeOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set, $state): void {
                            if (! in_array($state, $this->typesThatRequireGame(), true)) {
                                $set('game_id', null);
                            }
                        }),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Ativa',
                            'inactive' => 'Inativa',
                        ])
                        ->default('active')
                        ->required()
                        ->native(false),

                    Select::make('game_id')
                        ->label('Jogo vinculado')
                        ->options(fn (): array => $this->gameOptions())
                        ->searchable()
                        ->nullable()
                        ->visible(fn (Get $get): bool => in_array((string) $get('type'), $this->typesThatRequireGame(), true))
                        ->helperText('Obrigatório para missões por jogo, rodadas, ganho ou perda.'),

                    TextInput::make('target_amount')
                        ->label('Meta da missão')
                        ->numeric()
                        ->inputMode('decimal')
                        ->required()
                        ->minValue(0.01)
                        ->live(onBlur: true)
                        ->helperText(fn (Get $get): string => $this->targetHelper((string) $get('type'))),

                    TextInput::make('reward')
                        ->label('Recompensa')
                        ->prefix('R$')
                        ->numeric()
                        ->inputMode('decimal')
                        ->required()
                        ->minValue(0.01)
                        ->helperText('Valor creditado em bônus quando o usuário resgata a missão.'),

                    FileUpload::make('image')
                        ->label('Imagem da missão')
                        ->image()
                        ->required(! $isEdit)
                        ->helperText('Recomenda-se Tamanho 316x220')
                        ->saveUploadedFileUsing(
                            fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ];
    }

    private function normalizePayload(array $data, ?Mission $record = null): array
    {
        $type = (string) ($data['type'] ?? 'deposit');

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'type' => $type,
            'game_id' => in_array($type, $this->typesThatRequireGame(), true) ? ($data['game_id'] ?? null) : null,
            'target_amount' => (float) str_replace(',', '.', (string) ($data['target_amount'] ?? 0)),
            'reward' => (float) str_replace(',', '.', (string) ($data['reward'] ?? 0)),
            'image' => $this->normalizeUploadedPath($data['image'] ?? null) ?: $record?->image,
            'status' => $data['status'] ?? 'active',
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

    public function openPreview(Mission $record): void
    {
        $this->previewMission = $record;
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->previewMission = null;
        $this->showPreviewModal = false;
    }

    public function missionTypeOptions(): array
    {
        return [
            'deposit' => 'Missão de Depósito',
            'game_bet' => 'Missão por Jogo',
            'total_bet' => 'Missão de Aposta Total',
            'rounds_played' => 'Missão de Rodadas',
            'win_amount' => 'Missão de Ganho',
            'loss_amount' => 'Missão de Perda',
        ];
    }

    public function missionTypeLabel(?string $type): string
    {
        return match ($type) {
            'deposit' => 'DEPÓSITO',
            'game_bet' => 'JOGO',
            'total_bet' => 'APOSTA TOTAL',
            'rounds_played' => 'SPINS',
            'win_amount' => 'GANHOS',
            'loss_amount' => 'PERDAS',
            default => 'MISSÃO',
        };
    }

    public function shortDescriptionForType(?string $type, float|int|string|null $target = null): string
    {
        $target = (float) $target;

        return match ($type) {
            'rounds_played' => 'Aposte ' . number_format($target, 0, ',', '.') . ' spins nos jogos elegíveis.',
            'deposit' => 'Deposite ' . $this->money($target) . ' para concluir.',
            'win_amount' => 'Ganhe ' . $this->money($target) . ' em prêmios.',
            'loss_amount' => 'Tenha ' . $this->money($target) . ' em perdas no jogo vinculado.',
            'game_bet' => 'Aposte ' . $this->money($target) . ' no jogo vinculado.',
            'total_bet' => 'Aposte ' . $this->money($target) . ' durante o dia.',
            default => 'Complete o objetivo para liberar o bônus.',
        };
    }

    public function targetHelper(?string $type): string
    {
        return match ($type) {
            'rounds_played' => 'Informe a quantidade de rodadas/spins necessária.',
            'deposit' => 'Informe o valor de depósito aprovado necessário.',
            'win_amount' => 'Informe o valor de ganho necessário.',
            'loss_amount' => 'Informe o valor de perda necessário.',
            'game_bet' => 'Informe o valor apostado necessário no jogo vinculado.',
            'total_bet' => 'Informe o valor total apostado necessário no dia.',
            default => 'Informe a meta necessária para concluir a missão.',
        };
    }

    public function formatTarget(Mission $record): string
    {
        if ($record->type === 'rounds_played') {
            return number_format((float) $record->target_amount, 0, ',', '.') . ' spins';
        }

        return $this->money($record->target_amount);
    }

    public function gameOptions(): array
    {
        return DB::table('games')
            ->orderBy('game_name')
            ->pluck('game_name', 'game_id')
            ->toArray();
    }

    public function gameName(?string $gameId): string
    {
        if (blank($gameId)) {
            return 'Todos';
        }

        return (string) (DB::table('games')->where('game_id', $gameId)->value('game_name') ?: $gameId);
    }

    public function typesThatRequireGame(): array
    {
        return ['game_bet', 'rounds_played', 'win_amount', 'loss_amount'];
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

    public function stats(): array
    {
        return [
            'total' => Mission::query()->count(),
            'active' => Mission::query()->where('status', 'active')->count(),
            'inactive' => Mission::query()->where('status', 'inactive')->count(),
            'redeemed_today' => MissionUser::query()->where('redeemed', 1)->whereDate('updated_at', today())->count(),
            'redeemed_total' => MissionUser::query()->where('redeemed', 1)->count(),
            'reward_total' => (float) Mission::query()->sum('reward'),
            'last_update' => Mission::query()->latest('updated_at')->value('updated_at'),
        ];
    }

    public function money(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function clearMissionCache(): void
    {
        if (method_exists(Mission::class, 'clearMissionCaches')) {
            Mission::clearMissionCaches();
            return;
        }

        Cache::forget('missions:v1:active:list');
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}
