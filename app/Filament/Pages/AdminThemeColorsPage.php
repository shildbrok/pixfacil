<?php



namespace App\Filament\Pages;

use App\Models\ThemeColorToken;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminThemeColorsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-theme-colors-page';

    protected static ?string $title = 'Cores do Tema';
    protected static ?string $navigationLabel = 'Cores do Tema';
    protected static ?string $navigationGroup = 'Tema e Aparência';
    protected static ?string $navigationIcon = 'heroicon-o-swatch';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'cores-do-tema';

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
        $base = ThemeColorToken::query();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'editable' => (clone $base)->where('is_editable', true)->count(),
            'essentials' => (clone $base)->whereIn('token', $this->essentialTokens())->count(),
            'families' => (clone $base)->whereNotNull('family')->distinct('family')->count('family'),
            'occurrences' => (int) (clone $base)->sum('total_occurrences'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query($this->tokensQuery())
            ->defaultSort('sort_order')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('preview')
                    ->label('Cor')
                    ->state(fn (ThemeColorToken $record): string => (string) $record->color_value)
                    ->html()
                    ->formatStateUsing(function (?string $state): string {
                        $color = $state ?: 'transparent';

                        return '<div style="display:flex;align-items:center;gap:10px;min-width:150px">
                            <div style="width:34px;height:34px;border-radius:10px;border:1px solid rgba(148,163,184,.45);background:' . e($color) . ';box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)"></div>
                            <code style="font-size:12px">' . e($color) . '</code>
                        </div>';
                    }),

                TextColumn::make('label')
                    ->label('Token')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (ThemeColorToken $record): string => $record->token),

                TextColumn::make('family')
                    ->label('Família')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('group_name')
                    ->label('Grupo')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('css_variable')
                    ->label('Variável CSS')
                    ->copyable()
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('total_occurrences')
                    ->label('Uso')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_editable')
                    ->label('Editável')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('search')
                    ->label('Buscar')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Token, nome, variável ou cor'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $search = trim((string) ($data['value'] ?? ''));

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->where('token', 'like', "%{$search}%")
                                ->orWhere('label', 'like', "%{$search}%")
                                ->orWhere('css_variable', 'like', "%{$search}%")
                                ->orWhere('color_value', 'like', "%{$search}%");
                        });
                    }),

                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options([
                        'essentials' => 'Essenciais',
                        'alpha' => 'Transparências / Alpha',
                        'overlay' => 'Overlays',
                        'panel' => 'Painéis',
                        'misc' => 'Outras',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'essentials' => $query->whereIn('token', $this->essentialTokens()),
                            'alpha' => $query->where(fn (Builder $q) => $q->where('token', 'like', '%alpha%')->orWhere('group_name', 'like', '%alpha%')),
                            'overlay' => $query->where(fn (Builder $q) => $q->where('token', 'like', 'overlay-%')->orWhere('group_name', 'overlay')),
                            'panel' => $query->where(fn (Builder $q) => $q->where('token', 'like', 'panel-%')->orWhere('group_name', 'like', 'panel%')),
                            'misc' => $query->where(fn (Builder $q) => $q->where('token', 'like', 'misc-%')->orWhere('family', 'misc')->orWhere('group_name', 'unclassified')),
                            default => $query,
                        };
                    }),

                SelectFilter::make('family')
                    ->label('Família')
                    ->options(fn (): array => ThemeColorToken::query()
                        ->whereNotNull('family')
                        ->distinct()
                        ->orderBy('family')
                        ->pluck('family', 'family')
                        ->toArray()),

                SelectFilter::make('group_name')
                    ->label('Grupo')
                    ->options(fn (): array => ThemeColorToken::query()
                        ->whereNotNull('group_name')
                        ->distinct()
                        ->orderBy('group_name')
                        ->pluck('group_name', 'group_name')
                        ->toArray()),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Ativas',
                        '0' => 'Inativas',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->where('is_active', (bool) $data['value']);
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('apply_template')
                    ->label('Aplicar template')
                    ->icon('heroicon-o-swatch')
                    ->color('primary')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Select::make('template')
                            ->label('Modelo de tema')
                            ->options($this->templateOptions())
                            ->required()
                            ->helperText('Aplica um conjunto de cores padronizadas nos tokens principais.'),

                        Forms\Components\Toggle::make('only_editable')
                            ->label('Alterar somente cores editáveis')
                            ->default(true),

                        Forms\Components\Toggle::make('activate_tokens')
                            ->label('Ativar tokens aplicados')
                            ->default(true),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Aplicar template de tema?')
                    ->modalDescription('As cores dos tokens principais serão atualizadas conforme o modelo escolhido.')
                    ->modalSubmitActionLabel('Aplicar template')
                    ->action(function (array $data): void {
                        $this->applyTemplate(
                            (string) $data['template'],
                            (bool) ($data['only_editable'] ?? true),
                            (bool) ($data['activate_tokens'] ?? true),
                        );
                    }),

                Action::make('create_token')
                    ->label('Nova cor')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth('lg')
                    ->form($this->tokenForm())
                    ->action(function (array $data): void {
                        ThemeColorToken::create($this->normalizeTokenPayload($data));

                        Notification::make()
                            ->title('Cor cadastrada')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('edit_color')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->slideOver()
                    ->modalWidth('lg')
                    ->modalHeading(fn (ThemeColorToken $record): string => 'Editar cor: ' . $record->label)
                    ->form($this->tokenForm())
                    ->fillForm(fn (ThemeColorToken $record): array => [
                        'token' => $record->token,
                        'label' => $record->label,
                        'family' => $record->family,
                        'group_name' => $record->group_name,
                        'color_value' => $record->color_value,
                        'color_format' => $record->color_format,
                        'css_variable' => $record->css_variable,
                        'is_active' => (bool) $record->is_active,
                        'is_editable' => (bool) $record->is_editable,
                        'sort_order' => $record->sort_order,
                        'notes' => $record->notes,
                    ])
                    ->action(function (ThemeColorToken $record, array $data): void {
                        $record->update($this->normalizeTokenPayload($data));

                        Notification::make()
                            ->title('Cor atualizada')
                            ->success()
                            ->send();
                    }),

                Action::make('toggle_active')
                    ->label(fn (ThemeColorToken $record): string => $record->is_active ? 'Desativar' : 'Ativar')
                    ->icon(fn (ThemeColorToken $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (ThemeColorToken $record): string => $record->is_active ? 'warning' : 'success')
                    ->action(function (ThemeColorToken $record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->fresh()->is_active ? 'Cor ativada' : 'Cor desativada')
                            ->success()
                            ->send();
                    }),

                Action::make('info')
                    ->label('Informações')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalWidth('2xl')
                    ->modalHeading(fn (ThemeColorToken $record): string => 'Informações: ' . $record->label)
                    ->modalContent(fn (ThemeColorToken $record) => view('filament.pages.partials.theme-color-token-info-modal', [
                        'token' => $record,
                    ])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Ativar selecionadas')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),

                    BulkAction::make('deactivate')
                        ->label('Desativar selecionadas')
                        ->icon('heroicon-o-x-mark')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->emptyStateHeading('Nenhuma cor encontrada')
            ->emptyStateDescription('Cadastre uma nova cor ou aplique um template.');
    }

    private function tokensQuery(): Builder
    {
        return ThemeColorToken::query()
            ->orderBy('sort_order')
            ->orderByDesc('total_occurrences')
            ->orderBy('label');
    }

    private function tokenForm(): array
    {
        return [
            Forms\Components\Section::make('Identificação')
                ->schema([
                    Forms\Components\TextInput::make('token')
                        ->label('Token')
                        ->required()
                        ->maxLength(100)
                        ->helperText('Ex.: primary, background, surface'),

                    Forms\Components\TextInput::make('label')
                        ->label('Nome exibido')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\TextInput::make('family')
                        ->label('Família')
                        ->maxLength(80),

                    Forms\Components\TextInput::make('group_name')
                        ->label('Grupo')
                        ->maxLength(80),
                ])
                ->columns(2),

            Forms\Components\Section::make('Cor')
                ->schema([
                    Forms\Components\TextInput::make('color_value')
                        ->label('Valor CSS da cor')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Aceita HEX, rgba(), hsl(), transparent, black, gradients etc.'),

                    Forms\Components\TextInput::make('css_variable')
                        ->label('Variável CSS')
                        ->required()
                        ->maxLength(150)
                        ->helperText('Ex.: --color-primary'),

                    Forms\Components\Select::make('color_format')
                        ->label('Formato')
                        ->options([
                            'hex' => 'HEX',
                            'rgba' => 'RGBA',
                            'rgb' => 'RGB',
                            'hsl' => 'HSL',
                            'keyword' => 'Palavra CSS',
                            'gradient' => 'Gradiente',
                            'other' => 'Outro',
                        ])
                        ->default('hex')
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Comportamento')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Cor ativa')
                        ->default(true),

                    Forms\Components\Toggle::make('is_editable')
                        ->label('Permitir edição')
                        ->default(true),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordem')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    private function normalizeTokenPayload(array $data): array
    {
        $token = trim((string) ($data['token'] ?? ''));
        $cssVariable = trim((string) ($data['css_variable'] ?? ''));

        if ($cssVariable === '' && $token !== '') {
            $cssVariable = '--color-' . str_replace('_', '-', $token);
        }

        return [
            'token' => $token,
            'label' => trim((string) ($data['label'] ?? $token)),
            'family' => $data['family'] ?? null,
            'group_name' => $data['group_name'] ?? null,
            'color_value' => trim((string) ($data['color_value'] ?? '')),
            'color_format' => $data['color_format'] ?? $this->detectColorFormat((string) ($data['color_value'] ?? '')),
            'css_variable' => $cssVariable,
            'is_active' => ! empty($data['is_active']),
            'is_editable' => ! empty($data['is_editable']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function applyTemplate(string $template, bool $onlyEditable = true, bool $activateTokens = true): void
    {
        $templates = $this->templates();

        if (! isset($templates[$template])) {
            Notification::make()
                ->title('Template inválido')
                ->danger()
                ->send();

            return;
        }

        $palette = $templates[$template]['palette'];

        $query = ThemeColorToken::query();

        if ($onlyEditable) {
            $query->where('is_editable', true);
        }

        $count = 0;

        $query->orderBy('id')->get()->each(function (ThemeColorToken $record) use ($palette, $activateTokens, &$count): void {
            $newColor = $this->resolveTemplateColor($record, $palette);

            if ($newColor === null || $newColor === '') {
                return;
            }

            $payload = [
                'color_value' => $newColor,
                'color_format' => $this->detectColorFormat($newColor),
            ];

            if ($activateTokens) {
                $payload['is_active'] = true;
            }

            $record->update($payload);
            $count++;
        });

        Notification::make()
            ->title('Template aplicado')
            ->body($templates[$template]['name'] . " aplicado em {$count} cor(es).")
            ->success()
            ->send();
    }

    private function resolveTemplateColor(ThemeColorToken $record, array $palette): ?string
    {
        $token = strtolower((string) $record->token);
        $group = strtolower((string) $record->group_name);
        $family = strtolower((string) $record->family);
        $current = (string) $record->color_value;

        if ($token === 'transparent') {
            return 'transparent';
        }

        if ($token === 'black') {
            return $palette['black'];
        }

        if ($token === 'white') {
            return $palette['white'];
        }

        $direct = [
            'primary' => 'primary',
            'primary-soft' => 'primarySoft',
            'accent' => 'accent',
            'background' => 'background',
            'surface' => 'surface',
            'surface-2' => 'surface2',
            'text' => 'text',
            'text-muted' => 'textMuted',
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            'info' => 'info',
        ];

        if (isset($direct[$token])) {
            return $palette[$direct[$token]] ?? null;
        }

        $baseKey = match (true) {
            str_starts_with($group, 'primary-alpha') || str_starts_with($token, 'primary-alpha') => 'primary',
            str_starts_with($group, 'accent-alpha') || str_starts_with($token, 'accent-alpha') => 'accent',
            str_starts_with($group, 'success-alpha') || str_starts_with($token, 'success-alpha') => 'success',
            str_starts_with($group, 'danger-alpha') || str_starts_with($token, 'danger-alpha') => 'danger',
            str_starts_with($group, 'warning-alpha') || str_starts_with($token, 'warning-alpha') => 'warning',
            str_starts_with($group, 'info-alpha') || str_starts_with($token, 'info-alpha') => 'info',
            str_starts_with($group, 'text-muted-alpha') || str_starts_with($token, 'text-muted-alpha') => 'textMuted',
            str_starts_with($group, 'text-alpha') || str_starts_with($token, 'text-alpha') => 'text',
            str_starts_with($group, 'overlay') || str_starts_with($token, 'overlay-') => 'overlay',
            str_starts_with($group, 'panel-alpha') || str_starts_with($token, 'panel-alpha-deep') => 'background',
            str_starts_with($token, 'panel-alpha-surface') => 'surface2',
            str_starts_with($token, 'panel-alpha') => 'surface',
            default => null,
        };

        if ($baseKey !== null) {
            return $this->withPreservedAlpha($palette[$baseKey] ?? $current, $current);
        }

        $familyBaseKey = match ($family) {
            'yellow' => 'primary',
            'purple' => 'accent',
            'green' => 'success',
            'red' => 'danger',
            'orange' => 'warning',
            'blue' => 'info',
            'white' => 'text',
            'gray' => 'textMuted',
            'black' => 'black',
            'slate' => 'surface',
            'dark' => 'background',
            'misc' => 'misc',
            default => null,
        };

        if ($familyBaseKey !== null) {
            return $this->withPreservedAlpha($palette[$familyBaseKey] ?? $current, $current);
        }

        return $current;
    }

    private function withPreservedAlpha(string $baseColor, string $currentColor): string
    {
        $baseHex = $this->normalizeHexColor($baseColor);

        if ($baseHex === null) {
            return $baseColor;
        }

        $alpha = $this->extractAlphaHex($currentColor);

        return $alpha === null ? $baseHex : $baseHex . $alpha;
    }

    private function normalizeHexColor(string $color): ?string
    {
        $color = trim($color);

        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $color, $matches)) {
            $short = $matches[1];

            return '#' . $short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2];
        }

        if (preg_match('/^#([0-9A-Fa-f]{6})([0-9A-Fa-f]{2})?$/', $color, $matches)) {
            return '#' . strtoupper($matches[1]);
        }

        return null;
    }

    private function extractAlphaHex(string $color): ?string
    {
        $color = trim($color);

        if (preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})$/', $color, $matches)) {
            return strtolower($matches[1]);
        }

        if (preg_match('/^#[0-9A-Fa-f]{4}$/', $color)) {
            $alpha = substr($color, -1);

            return strtolower($alpha . $alpha);
        }

        return null;
    }

    public function templateOptions(): array
    {
        return collect($this->templates())
            ->mapWithKeys(fn (array $template, string $key): array => [$key => $template['name']])
            ->toArray();
    }

    public function templates(): array
    {
        return [
            'onda_pro' => [
                'name' => 'Onda Pro / Verde premium',
                'palette' => [
                    'primary' => '#00B91E',
                    'primarySoft' => '#063D1A',
                    'accent' => '#22C55E',
                    'background' => '#020617',
                    'surface' => '#0F172A',
                    'surface2' => '#111827',
                    'text' => '#F8FAFC',
                    'textMuted' => '#94A3B8',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#0F172A',
                    'misc' => '#64748B',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'midnight_blue' => [
                'name' => 'Midnight Blue / Azul cassino',
                'palette' => [
                    'primary' => '#3356FF',
                    'primarySoft' => '#0C2866',
                    'accent' => '#54BAF7',
                    'background' => '#020617',
                    'surface' => '#0B1220',
                    'surface2' => '#111C33',
                    'text' => '#E5E7EB',
                    'textMuted' => '#98A7B5',
                    'success' => '#0FF77D',
                    'warning' => '#F97316',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#0F172A',
                    'misc' => '#374151',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'royal_gold' => [
                'name' => 'Royal Gold / Cassino clássico',
                'palette' => [
                    'primary' => '#F5C542',
                    'primarySoft' => '#3A2A08',
                    'accent' => '#D97706',
                    'background' => '#09090B',
                    'surface' => '#18181B',
                    'surface2' => '#27272A',
                    'text' => '#FAFAFA',
                    'textMuted' => '#A1A1AA',
                    'success' => '#16A34A',
                    'warning' => '#F59E0B',
                    'danger' => '#DC2626',
                    'info' => '#0EA5E9',
                    'overlay' => '#000000',
                    'panel' => '#18181B',
                    'misc' => '#A16207',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'neon_casino' => [
                'name' => 'Neon Casino / Roxo e ciano',
                'palette' => [
                    'primary' => '#A855F7',
                    'primarySoft' => '#2E1065',
                    'accent' => '#06B6D4',
                    'background' => '#050014',
                    'surface' => '#140C26',
                    'surface2' => '#1F1633',
                    'text' => '#F5F3FF',
                    'textMuted' => '#A78BFA',
                    'success' => '#10B981',
                    'warning' => '#FBBF24',
                    'danger' => '#F43F5E',
                    'info' => '#22D3EE',
                    'overlay' => '#000000',
                    'panel' => '#140C26',
                    'misc' => '#8B5CF6',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'red_black' => [
                'name' => 'Red Black / Agressivo',
                'palette' => [
                    'primary' => '#EF233C',
                    'primarySoft' => '#3F0712',
                    'accent' => '#FFB703',
                    'background' => '#050505',
                    'surface' => '#111111',
                    'surface2' => '#1F1F1F',
                    'text' => '#FFFFFF',
                    'textMuted' => '#B3B3B3',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#111111',
                    'misc' => '#7F1D1D',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'black_luxury' => [
                'name' => 'Black Luxury / Preto absoluto',
                'palette' => [
                    'primary' => '#EAB308',
                    'primarySoft' => '#2A2104',
                    'accent' => '#FFFFFF',
                    'background' => '#000000',
                    'surface' => '#080808',
                    'surface2' => '#141414',
                    'text' => '#FFFFFF',
                    'textMuted' => '#A3A3A3',
                    'success' => '#22C55E',
                    'warning' => '#FACC15',
                    'danger' => '#EF4444',
                    'info' => '#60A5FA',
                    'overlay' => '#000000',
                    'panel' => '#080808',
                    'misc' => '#525252',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'white_clean' => [
                'name' => 'White Clean / Branco moderno',
                'palette' => [
                    'primary' => '#2563EB',
                    'primarySoft' => '#DBEAFE',
                    'accent' => '#14B8A6',
                    'background' => '#FFFFFF',
                    'surface' => '#F8FAFC',
                    'surface2' => '#E2E8F0',
                    'text' => '#0F172A',
                    'textMuted' => '#64748B',
                    'success' => '#16A34A',
                    'warning' => '#D97706',
                    'danger' => '#DC2626',
                    'info' => '#0284C7',
                    'overlay' => '#000000',
                    'panel' => '#FFFFFF',
                    'misc' => '#475569',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'ice_white' => [
                'name' => 'Ice White / Branco gelo',
                'palette' => [
                    'primary' => '#06B6D4',
                    'primarySoft' => '#CFFAFE',
                    'accent' => '#6366F1',
                    'background' => '#F8FAFC',
                    'surface' => '#FFFFFF',
                    'surface2' => '#E0F2FE',
                    'text' => '#0C1222',
                    'textMuted' => '#64748B',
                    'success' => '#059669',
                    'warning' => '#CA8A04',
                    'danger' => '#E11D48',
                    'info' => '#0284C7',
                    'overlay' => '#000000',
                    'panel' => '#FFFFFF',
                    'misc' => '#94A3B8',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'graphite' => [
                'name' => 'Graphite / Cinza profissional',
                'palette' => [
                    'primary' => '#94A3B8',
                    'primarySoft' => '#1E293B',
                    'accent' => '#38BDF8',
                    'background' => '#111827',
                    'surface' => '#1F2937',
                    'surface2' => '#374151',
                    'text' => '#F9FAFB',
                    'textMuted' => '#9CA3AF',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#60A5FA',
                    'overlay' => '#000000',
                    'panel' => '#1F2937',
                    'misc' => '#6B7280',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'emerald_dark' => [
                'name' => 'Emerald Dark / Verde escuro',
                'palette' => [
                    'primary' => '#10B981',
                    'primarySoft' => '#064E3B',
                    'accent' => '#A7F3D0',
                    'background' => '#022C22',
                    'surface' => '#064E3B',
                    'surface2' => '#065F46',
                    'text' => '#ECFDF5',
                    'textMuted' => '#6EE7B7',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#F43F5E',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#064E3B',
                    'misc' => '#047857',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'purple_royal' => [
                'name' => 'Purple Royal / Roxo premium',
                'palette' => [
                    'primary' => '#7C3AED',
                    'primarySoft' => '#2E1065',
                    'accent' => '#F0ABFC',
                    'background' => '#120A24',
                    'surface' => '#1E1237',
                    'surface2' => '#2A174F',
                    'text' => '#FAF5FF',
                    'textMuted' => '#C084FC',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#1E1237',
                    'misc' => '#6D28D9',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'pink_vip' => [
                'name' => 'Pink VIP / Rosa neon',
                'palette' => [
                    'primary' => '#EC4899',
                    'primarySoft' => '#500724',
                    'accent' => '#F9A8D4',
                    'background' => '#13030B',
                    'surface' => '#2A0A1A',
                    'surface2' => '#3B0A24',
                    'text' => '#FDF2F8',
                    'textMuted' => '#F9A8D4',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#E11D48',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#2A0A1A',
                    'misc' => '#DB2777',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'orange_fire' => [
                'name' => 'Orange Fire / Laranja fogo',
                'palette' => [
                    'primary' => '#F97316',
                    'primarySoft' => '#431407',
                    'accent' => '#FACC15',
                    'background' => '#120A05',
                    'surface' => '#1C1208',
                    'surface2' => '#2A1A0B',
                    'text' => '#FFF7ED',
                    'textMuted' => '#FDBA74',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#DC2626',
                    'info' => '#0EA5E9',
                    'overlay' => '#000000',
                    'panel' => '#1C1208',
                    'misc' => '#C2410C',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'cyan_matrix' => [
                'name' => 'Cyan Matrix / Tech ciano',
                'palette' => [
                    'primary' => '#06B6D4',
                    'primarySoft' => '#083344',
                    'accent' => '#22C55E',
                    'background' => '#011014',
                    'surface' => '#062A33',
                    'surface2' => '#0E3A46',
                    'text' => '#ECFEFF',
                    'textMuted' => '#67E8F9',
                    'success' => '#22C55E',
                    'warning' => '#EAB308',
                    'danger' => '#F43F5E',
                    'info' => '#22D3EE',
                    'overlay' => '#000000',
                    'panel' => '#062A33',
                    'misc' => '#0891B2',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'coffee_brown' => [
                'name' => 'Coffee Brown / Marrom elegante',
                'palette' => [
                    'primary' => '#A16207',
                    'primarySoft' => '#422006',
                    'accent' => '#F59E0B',
                    'background' => '#120A04',
                    'surface' => '#1C1207',
                    'surface2' => '#2A1C0C',
                    'text' => '#FEF3C7',
                    'textMuted' => '#D6A75C',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#1C1207',
                    'misc' => '#92400E',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'silver_light' => [
                'name' => 'Silver Light / Prata claro',
                'palette' => [
                    'primary' => '#64748B',
                    'primarySoft' => '#E2E8F0',
                    'accent' => '#2563EB',
                    'background' => '#F1F5F9',
                    'surface' => '#FFFFFF',
                    'surface2' => '#CBD5E1',
                    'text' => '#0F172A',
                    'textMuted' => '#475569',
                    'success' => '#16A34A',
                    'warning' => '#CA8A04',
                    'danger' => '#DC2626',
                    'info' => '#0284C7',
                    'overlay' => '#000000',
                    'panel' => '#FFFFFF',
                    'misc' => '#94A3B8',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'forest_gold' => [
                'name' => 'Forest Gold / Floresta dourada',
                'palette' => [
                    'primary' => '#65A30D',
                    'primarySoft' => '#1A2E05',
                    'accent' => '#EAB308',
                    'background' => '#071207',
                    'surface' => '#102010',
                    'surface2' => '#182F13',
                    'text' => '#F7FEE7',
                    'textMuted' => '#BEF264',
                    'success' => '#22C55E',
                    'warning' => '#EAB308',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#102010',
                    'misc' => '#4D7C0F',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'ocean_dark' => [
                'name' => 'Ocean Dark / Oceano escuro',
                'palette' => [
                    'primary' => '#0EA5E9',
                    'primarySoft' => '#082F49',
                    'accent' => '#14B8A6',
                    'background' => '#02111F',
                    'surface' => '#06233A',
                    'surface2' => '#0B3554',
                    'text' => '#F0F9FF',
                    'textMuted' => '#7DD3FC',
                    'success' => '#10B981',
                    'warning' => '#F59E0B',
                    'danger' => '#F43F5E',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#06233A',
                    'misc' => '#0369A1',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'lava_dark' => [
                'name' => 'Lava Dark / Vermelho lava',
                'palette' => [
                    'primary' => '#DC2626',
                    'primarySoft' => '#450A0A',
                    'accent' => '#F97316',
                    'background' => '#0F0303',
                    'surface' => '#1A0707',
                    'surface2' => '#2B0D0D',
                    'text' => '#FEF2F2',
                    'textMuted' => '#FCA5A5',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#1A0707',
                    'misc' => '#991B1B',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'bet_green_light' => [
                'name' => 'Bet Green Light / Verde claro',
                'palette' => [
                    'primary' => '#16A34A',
                    'primarySoft' => '#DCFCE7',
                    'accent' => '#84CC16',
                    'background' => '#F7FEE7',
                    'surface' => '#FFFFFF',
                    'surface2' => '#D9F99D',
                    'text' => '#052E16',
                    'textMuted' => '#3F6212',
                    'success' => '#16A34A',
                    'warning' => '#CA8A04',
                    'danger' => '#DC2626',
                    'info' => '#0284C7',
                    'overlay' => '#000000',
                    'panel' => '#FFFFFF',
                    'misc' => '#65A30D',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'indigo_night' => [
                'name' => 'Indigo Night / Índigo noturno',
                'palette' => [
                    'primary' => '#4F46E5',
                    'primarySoft' => '#1E1B4B',
                    'accent' => '#818CF8',
                    'background' => '#090B24',
                    'surface' => '#12143A',
                    'surface2' => '#1C1F55',
                    'text' => '#EEF2FF',
                    'textMuted' => '#A5B4FC',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#12143A',
                    'misc' => '#4338CA',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'amethyst_dark' => [
                'name' => 'Amethyst Dark / Ametista',
                'palette' => [
                    'primary' => '#9333EA',
                    'primarySoft' => '#3B0764',
                    'accent' => '#D946EF',
                    'background' => '#12051F',
                    'surface' => '#1D0B2E',
                    'surface2' => '#2B1045',
                    'text' => '#FAE8FF',
                    'textMuted' => '#E879F9',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#1D0B2E',
                    'misc' => '#7E22CE',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'turquoise_light' => [
                'name' => 'Turquoise Light / Turquesa claro',
                'palette' => [
                    'primary' => '#0D9488',
                    'primarySoft' => '#CCFBF1',
                    'accent' => '#06B6D4',
                    'background' => '#F0FDFA',
                    'surface' => '#FFFFFF',
                    'surface2' => '#99F6E4',
                    'text' => '#042F2E',
                    'textMuted' => '#0F766E',
                    'success' => '#16A34A',
                    'warning' => '#D97706',
                    'danger' => '#DC2626',
                    'info' => '#0284C7',
                    'overlay' => '#000000',
                    'panel' => '#FFFFFF',
                    'misc' => '#14B8A6',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'sand_casino' => [
                'name' => 'Sand Casino / Areia premium',
                'palette' => [
                    'primary' => '#D97706',
                    'primarySoft' => '#FEF3C7',
                    'accent' => '#A16207',
                    'background' => '#FFFBEB',
                    'surface' => '#FFFFFF',
                    'surface2' => '#FDE68A',
                    'text' => '#451A03',
                    'textMuted' => '#92400E',
                    'success' => '#16A34A',
                    'warning' => '#D97706',
                    'danger' => '#DC2626',
                    'info' => '#0284C7',
                    'overlay' => '#000000',
                    'panel' => '#FFFFFF',
                    'misc' => '#B45309',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'cyber_yellow' => [
                'name' => 'Cyber Yellow / Amarelo tech',
                'palette' => [
                    'primary' => '#FACC15',
                    'primarySoft' => '#3A3004',
                    'accent' => '#22D3EE',
                    'background' => '#070700',
                    'surface' => '#151504',
                    'surface2' => '#242407',
                    'text' => '#FEFCE8',
                    'textMuted' => '#FDE047',
                    'success' => '#22C55E',
                    'warning' => '#FACC15',
                    'danger' => '#EF4444',
                    'info' => '#22D3EE',
                    'overlay' => '#000000',
                    'panel' => '#151504',
                    'misc' => '#A16207',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
            'minimal_dark' => [
                'name' => 'Minimal Dark / Neutro escuro',
                'palette' => [
                    'primary' => '#E5E7EB',
                    'primarySoft' => '#27272A',
                    'accent' => '#A1A1AA',
                    'background' => '#09090B',
                    'surface' => '#18181B',
                    'surface2' => '#27272A',
                    'text' => '#FAFAFA',
                    'textMuted' => '#A1A1AA',
                    'success' => '#22C55E',
                    'warning' => '#F59E0B',
                    'danger' => '#EF4444',
                    'info' => '#38BDF8',
                    'overlay' => '#000000',
                    'panel' => '#18181B',
                    'misc' => '#71717A',
                    'black' => '#000000',
                    'white' => '#FFFFFF',
                ],
            ],
        ];
    }

    private function essentialTokens(): array
    {
        return [
            'primary',
            'primary-soft',
            'background',
            'surface',
            'surface-2',
            'text',
            'text-muted',
            'success',
            'warning',
            'danger',
            'info',
            'accent',
            'black',
            'transparent',
        ];
    }

    private function labelForToken(string $token): string
    {
        return str($token)
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();
    }

    private function sortForToken(string $token): int
    {
        $index = array_search($token, $this->essentialTokens(), true);

        return $index === false ? 100 : $index + 1;
    }

    private function detectColorFormat(string $value): string
    {
        $value = trim($value);
        $lower = strtolower($value);

        if (str_starts_with($value, '#')) {
            return 'hex';
        }

        if (str_starts_with($lower, 'rgba')) {
            return 'rgba';
        }

        if (str_starts_with($lower, 'rgb')) {
            return 'rgb';
        }

        if (str_starts_with($lower, 'hsl')) {
            return 'hsl';
        }

        if (str_contains($lower, 'gradient')) {
            return 'gradient';
        }

        return 'keyword';
    }

}