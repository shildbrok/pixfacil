<?php



namespace App\Filament\Pages;

use App\Models\Promocao;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminPromotionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-promotions-page';

    protected static ?string $title = 'Campanhas Promocionais';
    protected static ?string $navigationLabel = 'Campanhas Promocionais';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'campanhas-promocionais';

    public ?Promocao $previewPromotion = null;
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
                Tables\Actions\Action::make('create_promotion')
                    ->label('Nova campanha')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading('Criar campanha promocional')
                    ->form($this->promotionFormSchema())
                    ->action(function (array $data): void {
                        Promocao::query()->create($this->normalizePayload($data));

                        Notification::make()
                            ->title('Campanha criada')
                            ->body('A promoção foi cadastrada e já pode aparecer no frontend.')
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                ImageColumn::make('imagem')
                    ->label('Imagem')
                    ->getStateUsing(fn (Promocao $record): ?string => $this->imageUrl($record->imagem))
                    ->height(64)
                    ->width(110)
                    ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 12px;']),

                Tables\Columns\TextColumn::make('titulo')
                    ->label('Campanha')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Promocao $record): string => $this->detectType($record)),

                Tables\Columns\TextColumn::make('link')
                    ->label('Link')
                    ->url(fn (Promocao $record): ?string => $record->link)
                    ->openUrlInNewTab()
                    ->limit(44)
                    ->tooltip(fn (Promocao $record): ?string => $record->link)
                    ->placeholder('Sem link'),

                Tables\Columns\TextColumn::make('regras_html')
                    ->label('Regras')
                    ->state(fn (Promocao $record): string => filled($record->regras_html) ? 'Configuradas' : 'Sem regras')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Configuradas' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('com_regras')
                    ->label('Com regras')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('regras_html')->where('regras_html', '!=', '')),

                Tables\Filters\Filter::make('sem_regras')
                    ->label('Sem regras')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('regras_html')->orWhere('regras_html', ''))),

                Tables\Filters\Filter::make('com_link')
                    ->label('Com link')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('link')->where('link', '!=', '')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Promocao $record) => $this->openPreview($record)),

                Tables\Actions\Action::make('edit_promotion')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (Promocao $record): string => 'Editar campanha: ' . $record->titulo)
                    ->fillForm(fn (Promocao $record): array => [
                        'titulo' => $record->titulo,
                        'link' => $record->link,
                        'imagem' => $this->normalizeFileUploadStateForForm($record->imagem),
                        'regras_html' => $record->regras_html,
                    ])
                    ->form($this->promotionFormSchema(isEdit: true))
                    ->action(function (Promocao $record, array $data): void {
                        $record->update($this->normalizePayload($data, $record));

                        Notification::make()
                            ->title('Campanha atualizada')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir selecionadas'),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function query(): Builder
    {
        return Promocao::query();
    }

    private function promotionFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Como a página Vue lê a promoção')
                ->description('A página do cliente não recebe tipo nem descrição curta do banco. Ela calcula tudo pelo título.')
                ->schema([
                    Placeholder::make('vue_type_preview')
                        ->label('Tipo detectado no frontend')
                        ->content(fn (Get $get): HtmlString => new HtmlString(
                            '<span style="display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:rgba(249, 115, 22,.14);color:#fdba74;font-weight:900;font-size:12px">'
                            . e($this->detectTypeFromTitle((string) $get('titulo')))
                            . '</span>'
                        )),

                    Placeholder::make('vue_short_description')
                        ->label('Descrição curta que o Vue irá mostrar')
                        ->content(fn (Get $get): string => $this->shortDescriptionForType(
                            $this->detectTypeFromTitle((string) $get('titulo'))
                        )),

                    Placeholder::make('vue_keywords')
                        ->label('Palavras que mudam o tipo')
                        ->content(new HtmlString('
                            <div style="display:grid;gap:6px;color:#d4d4d4;font-size:12px;line-height:1.55">
                                <div><strong style="color:#fff">Bônus de depósito:</strong> título contendo depósito, deposito ou deposit.</div>
                                <div><strong style="color:#fff">Cashback:</strong> título contendo cashback.</div>
                                <div><strong style="color:#fff">Giros grátis:</strong> título contendo giro, spin ou rodada.</div>
                                <div><strong style="color:#fff">Bônus de boas-vindas:</strong> título contendo bem-vindo ou boas-vindas.</div>
                                <div><strong style="color:#fff">Promoção especial:</strong> qualquer outro título.</div>
                            </div>
                        '))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Identidade da campanha')
                ->description('Estes são exatamente os dados usados pela página : título, imagem, link e regras.')
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título da campanha')
                        ->placeholder('Ex.: Bônus de depósito 100%')
                        ->required()
                        ->live(onBlur: true)
                        ->maxLength(191)
                        ->helperText('O título define automaticamente o tipo visual no frontend. Use palavras como depósito, cashback, giros, rodadas ou boas-vindas.')
                        ->columnSpanFull(),

                    TextInput::make('link')
                        ->label('Link de ativação / participação')
                        ->placeholder('https://seudominio.com/promocao')
                        ->url()
                        ->maxLength(255)
                        ->helperText('Opcional. Se preenchido, mostra “Ativação online” e o botão “Participar da promoção”.')
                        ->columnSpanFull(),

                    FileUpload::make('imagem')
                        ->label('Imagem da campanha')
                        ->image()
                        ->required(! $isEdit)
                        ->helperText('Obrigatório para o card. Recomendado: 895x455.')
                        ->saveUploadedFileUsing(
                            fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Regras completas')
                ->description('Se preencher este campo, o Vue mostra o botão “Ver regras completas” e abre o popup.')
                ->schema([
                    RichEditor::make('regras_html')
                        ->label('Regras da promoção')
                        ->placeholder('Descreva rollover, validade, elegibilidade, jogos participantes e limite de uso.')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'bulletList',
                            'orderedList',
                            'link',
                            'blockquote',
                            'codeBlock',
                            'undo',
                            'redo',
                        ])
                        ->helperText('Opcional. Se vazio, o botão de regras não aparece no frontend.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    private function normalizePayload(array $data, ?Promocao $record = null): array
    {
        return [
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'link' => filled($data['link'] ?? null) ? trim((string) $data['link']) : null,
            'imagem' => $this->normalizeUploadedPath($data['imagem'] ?? null) ?: $record?->imagem,
            'regras_html' => $data['regras_html'] ?? null,
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

    public function openPreview(Promocao $record): void
    {
        $this->previewPromotion = $record;
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->previewPromotion = null;
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

    public function detectTypeFromTitle(?string $title): string
    {
        $title = mb_strtolower((string) $title);

        return match (true) {
            str_contains($title, 'depósito') || str_contains($title, 'deposito') || str_contains($title, 'deposit') => 'Bônus de depósito',
            str_contains($title, 'cashback') => 'Cashback',
            str_contains($title, 'giro') || str_contains($title, 'spin') || str_contains($title, 'rodada') => 'Giros grátis',
            str_contains($title, 'bem-vindo') || str_contains($title, 'boas-vindas') => 'Bônus de boas-vindas',
            default => 'Promoção especial',
        };
    }

    public function shortDescriptionForType(string $typeLabel): string
    {
        return match ($typeLabel) {
            'Bônus de depósito' => 'Realize um depósito dentro do período da campanha e receba um bônus extra automaticamente na sua conta.',
            'Cashback' => 'Receba parte das suas perdas de volta em forma de bônus para continuar jogando com mais fôlego.',
            'Giros grátis' => 'Ative a oferta, entre nos jogos elegíveis e aproveite giros extras sem consumir o saldo real.',
            'Bônus de boas-vindas' => 'Oferta dedicada a novos jogadores para começar na plataforma com saldo extra e mais chances de ganhar.',
            default => 'Campanha especial da plataforma com condições diferenciadas de bônus, validade e jogos participantes.',
        };
    }

    public function detectType(Promocao $record): string
    {
        return $this->detectTypeFromTitle($record->titulo);
    }

    public function stats(): array
    {
        return [
            'total' => Promocao::query()->count(),
            'with_rules' => Promocao::query()->whereNotNull('regras_html')->where('regras_html', '!=', '')->count(),
            'with_link' => Promocao::query()->whereNotNull('link')->where('link', '!=', '')->count(),
            'without_image' => Promocao::query()->where(fn (Builder $query) => $query->whereNull('imagem')->orWhere('imagem', ''))->count(),
            'updated_today' => Promocao::query()->whereDate('updated_at', today())->count(),
            'last_update' => Promocao::query()->latest('updated_at')->value('updated_at'),
        ];
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }
}