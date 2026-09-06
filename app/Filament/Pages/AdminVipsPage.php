<?php

namespace App\Filament\Pages;

use App\Models\MissionUser;
use App\Models\UserVip;
use App\Models\Vip;
use App\Support\AdminActionGuard;
use App\Support\AdminAudit;
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
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminVipsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.admin-vips-page';
    protected static ?string $title = 'Níveis VIP';
    protected static ?string $navigationLabel = 'Níveis VIP';
    protected static ?string $navigationGroup = 'Promoções e Benefícios';
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'niveis-vip';

    public ?Vip $previewVip = null;
    public bool $showPreviewModal = false;

    private const AUDIT_FIELDS = ['title', 'description', 'required_missions', 'weekly_reward', 'image'];

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
                Tables\Actions\Action::make('create_vip')
                    ->label('Novo nível VIP')->icon('heroicon-o-plus')->color('primary')->slideOver()->modalWidth('3xl')->modalHeading('Criar nível VIP')
                    ->form($this->vipFormSchema())
                    ->action(function (array $data): void {
                        if (! $this->confirmAdmin($data)) return;
                        unset($data['admin_password']);
                        $vip = Vip::query()->create($this->normalizePayload($data));
                        AdminAudit::log('vip.create', $vip, [], $vip->only(self::AUDIT_FIELDS), 'Criou nível VIP administrativo.');
                        Notification::make()->title('Nível VIP criado')->body('O nível foi cadastrado com sucesso.')->success()->send();
                    }),
            ])
            ->columns([
                ImageColumn::make('image')->label('Imagem')->getStateUsing(fn (Vip $record): ?string => $this->imageUrl($record->image))
                    ->height(64)->width(64)->extraImgAttributes(['style' => 'object-fit: contain; border-radius: 999px; background:#020617;']),
                Tables\Columns\TextColumn::make('title')->label('Nível VIP')->searchable()->sortable()->weight('bold')
                    ->description(fn (Vip $record): string => $record->description ? mb_strimwidth(strip_tags($record->description), 0, 72, '...') : 'Sem descrição'),
                Tables\Columns\TextColumn::make('required_missions')->label('Pontos / missões')->sortable()->alignCenter()
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.') . ' pts'),
                Tables\Columns\TextColumn::make('weekly_reward')->label('Bônus semanal')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('users_count')->label('Resgates')->counts('users')->alignCenter()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Atualizado')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('sem_imagem')->label('Sem imagem')->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('image')->orWhere('image', ''))),
                Tables\Filters\Filter::make('com_recompensa')->label('Com recompensa')->query(fn (Builder $query): Builder => $query->where('weekly_reward', '>', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')->label('Visualizar')->icon('heroicon-o-eye')->color('gray')->action(fn (Vip $record) => $this->openPreview($record)),
                Tables\Actions\Action::make('edit_vip')
                    ->label('Editar')->icon('heroicon-o-pencil-square')->color('info')->slideOver()->modalWidth('3xl')
                    ->modalHeading(fn (Vip $record): string => 'Editar nível: ' . $record->title)
                    ->fillForm(fn (Vip $record): array => [
                        'title' => $record->title,
                        'description' => $record->description,
                        'required_missions' => $record->required_missions,
                        'weekly_reward' => $record->weekly_reward,
                        'image' => $this->normalizeFileUploadStateForForm($record->image),
                        'admin_password' => null,
                    ])
                    ->form($this->vipFormSchema(isEdit: true))
                    ->action(function (Vip $record, array $data): void {
                        if (! $this->confirmAdmin($data)) return;
                        unset($data['admin_password']);
                        $before = $record->only(self::AUDIT_FIELDS);
                        $record->update($this->normalizePayload($data, $record));
                        $fresh = $record->fresh();
                        AdminAudit::log('vip.update', $fresh, $before, $fresh->only(self::AUDIT_FIELDS), 'Atualizou nível VIP administrativo.');
                        Notification::make()->title('Nível VIP atualizado')->success()->send();
                    }),
                Tables\Actions\Action::make('delete_vip')
                    ->label('Excluir')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->form([$this->adminPinField()])
                    ->action(function (Vip $record, array $data): void {
                        if (! $this->confirmAdmin($data)) return;
                        $before = $record->only(self::AUDIT_FIELDS);
                        AdminAudit::log('vip.delete', $record, $before, [], 'Excluiu nível VIP administrativo.');
                        $record->delete();
                        Notification::make()->title('Nível VIP excluído')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('required_missions', 'asc')
            ->striped();
    }

    private function query(): Builder
    {
        return Vip::query()->withCount('users');
    }

    private function vipFormSchema(bool $isEdit = false): array
    {
        return [
            Section::make('Como a página Vue usa o VIP')
                ->description('O frontend lê os níveis em ordem crescente de pontos necessários e calcula atual/próximo nível pelo progresso do usuário.')
                ->schema([
                    Placeholder::make('vue_current_preview')->label('Texto no card Vue')
                        ->content(fn (Get $get): HtmlString => new HtmlString(
                            '<div style="display:grid;gap:6px;color:#cbd5e1;font-size:12px">'
                            . '<div><strong style="color:#fff">' . e((string) ($get('title') ?: 'VIP Inicial')) . '</strong></div>'
                            . '<div>' . e(number_format((int) ($get('required_missions') ?: 0), 0, ',', '.')) . ' pts necessários</div>'
                            . '<div>Bônus semanal até <strong style="color:#22c55e">' . e($this->money((float) ($get('weekly_reward') ?: 0))) . '</strong></div>'
                            . '</div>'
                        )),
                    Placeholder::make('vue_rules')->label('Regras do frontend')->content(new HtmlString('
                        <div style="display:grid;gap:6px;color:#cbd5e1;font-size:12px;line-height:1.55">
                            <div><strong style="color:#fff">Pontos necessários:</strong> o Vue mostra como “pts necessários”.</div>
                            <div><strong style="color:#fff">Progresso:</strong> calculado com base nas missões resgatadas pelo usuário.</div>
                            <div><strong style="color:#fff">Recompensa:</strong> paga semanalmente em bônus, somente no VIP atual.</div>
                            <div><strong style="color:#fff">Ordem:</strong> cadastre do menor para o maior requisito.</div>
                        </div>'))->columnSpanFull(),
                ])->columns(2),
            Section::make('Informações do nível VIP')
                ->description('Configure nome, descrição, pontos necessários, bônus semanal e imagem do nível.')
                ->schema([
                    TextInput::make('title')->label('Nome do nível')->placeholder('Ex.: VIP Bronze')->required()->live(onBlur: true)->maxLength(255),
                    TextInput::make('required_missions')->label('Pontos necessários')->placeholder('Quantidade de missões/resgates necessários')->numeric()->inputMode('numeric')->required()->minValue(0)->live(onBlur: true)
                        ->helperText('O controller compara este valor com a quantidade de missões resgatadas pelo usuário.'),
                    TextInput::make('weekly_reward')->label('Bônus semanal')->prefix('R$')->placeholder('Valor pago semanalmente')->numeric()->inputMode('decimal')->required()->minValue(0)->live(onBlur: true),
                    Textarea::make('description')->label('Descrição')->placeholder('Descreva os benefícios deste nível VIP')->rows(4)->columnSpanFull(),
                    FileUpload::make('image')->label('Imagem do nível')->image()->required(! $isEdit)->imagePreviewHeight('120')
                        ->helperText('O Vue renderiza a imagem via /storage/{image}. Recomendado: imagem quadrada/medalha PNG.')
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null)->columnSpanFull(),
                ])->columns(3),
            Section::make('Confirmação administrativa')->description('Alterações de VIP afetam progressão e recompensas financeiras.')
                ->schema([$this->adminPinField()]),
        ];
    }

    private function adminPinField(): TextInput
    {
        return TextInput::make('admin_password')->label('PIN administrativo')->password()->numeric()->length(6)->required();
    }

    private function confirmAdmin(array $data): bool
    {
        if (app(AdminActionGuard::class)->confirm((string) ($data['admin_password'] ?? ''))) return true;
        Notification::make()->title('Acesso negado')->body('PIN administrativo inválido.')->danger()->send();
        return false;
    }

    private function normalizePayload(array $data, ?Vip $record = null): array
    {
        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'required_missions' => max(0, (int) ($data['required_missions'] ?? 0)),
            'weekly_reward' => (float) str_replace(',', '.', (string) ($data['weekly_reward'] ?? 0)),
            'image' => $this->normalizeUploadedPath($data['image'] ?? null) ?: $record?->image,
        ];
    }

    private function normalizeFileUploadStateForForm(mixed $value): ?array
    {
        if (blank($value)) return null;
        if (is_array($value)) return array_values(array_filter($value));
        if (is_string($value)) return [$value];
        return null;
    }

    private function normalizeUploadedPath(mixed $value): ?string
    {
        if (blank($value)) return null;
        if (is_string($value)) return ltrim($value, '/');
        if ($value instanceof TemporaryUploadedFile) {
            $uploaded = \Helper::upload($value);
            return is_array($uploaded) ? ($uploaded['path'] ?? null) : null;
        }
        if (is_array($value)) return $this->normalizeUploadedPath(reset($value));
        return null;
    }

    public function openPreview(Vip $record): void { $this->previewVip = $record; $this->showPreviewModal = true; }
    public function closePreview(): void { $this->previewVip = null; $this->showPreviewModal = false; }

    public function imageUrl(?string $image): ?string
    {
        if (! filled($image)) return null;
        $image = ltrim((string) $image, '/');
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) return $image;
        if (str_starts_with($image, 'storage/')) return asset($image);
        if (str_starts_with($image, 'uploads/')) return asset('storage/' . $image);
        return asset('storage/' . $image);
    }

    public function stats(): array
    {
        return [
            'total' => Vip::query()->count(),
            'reward_sum' => (float) Vip::query()->sum('weekly_reward'),
            'lowest_points' => Vip::query()->min('required_missions') ?? 0,
            'highest_points' => Vip::query()->max('required_missions') ?? 0,
            'claims_today' => UserVip::query()->whereDate('last_reward_claimed_at', today())->count(),
            'claims_total' => UserVip::query()->whereNotNull('last_reward_claimed_at')->count(),
            'completed_missions' => MissionUser::query()->where('redeemed', 1)->count(),
            'last_update' => Vip::query()->latest('updated_at')->value('updated_at'),
        ];
    }

    public function money(float|int|string|null $value): string { return 'R$ ' . number_format((float) $value, 2, ',', '.'); }

    public function nextVip(?Vip $vip): ?Vip
    {
        if (! $vip) return null;
        return Vip::query()->where('required_missions', '>', (int) $vip->required_missions)->orderBy('required_missions')->first();
    }

    public function getTableRecordKey(Model $record): string { return (string) $record->getKey(); }
}
