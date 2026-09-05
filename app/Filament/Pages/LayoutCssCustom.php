<?php



namespace App\Filament\Pages;

use App\Helpers\Core;
use App\Models\CustomLayout;
use App\Services\CacheNuker;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LayoutCssCustom extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.layout-css-custom';

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Configurações Secundárias';
    protected static ?string $modelLabel = 'Configurações Secundárias';
    protected static ?string $title = 'Configurações Secundárias';
    protected static ?string $slug = 'custom-layout';

    public ?array $data = [];
    public CustomLayout $custom;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(): void
    {
        $this->custom = CustomLayout::first() ?? new CustomLayout();

        if (! $this->custom->exists) {
            $this->custom->save();
        }

        $data = $this->custom->toArray();

        foreach ($this->imageFields() as $field) {
            $data[$field] = $this->normalizeFileUploadStateForForm($data[$field] ?? null);
        }

        $this->form->fill($data);
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Tabs::make('Configurações secundárias')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tabs\Tab::make('Chat e links')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('JivoChat')
                                    ->description('Configure o token/script do atendimento online.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('jivochat_help')
                                            ->label('Ajuda')
                                            ->content(new \Illuminate\Support\HtmlString(
                                                '<a href="https://www.jivochat.com.br/?partner_id=47634" target="_blank" style="display:inline-flex;align-items:center;justify-content:center;padding:9px 13px;border-radius:12px;background:#00b91e;color:#fff;font-weight:800;font-size:13px">Abrir site do JivoChat</a>'
                                            )),

                                        TextInput::make('token_jivochat')
                                            ->label('Token/script do JivoChat')
                                            ->placeholder('//code.jivosite.com/widget/lmxxxxxxxx')
                                            ->helperText('Cole o token/script fornecido pelo JivoChat.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Links complementares')
                                    ->description('Links exibidos em áreas de suporte, app e redes sociais.')
                                    ->schema([
                                        TextInput::make('link_suporte')->label('Suporte')->url(),
                                        TextInput::make('link_lincenca')->label('Licença')->url(),
                                        TextInput::make('link_app')->label('Aplicativo')->url(),
                                        TextInput::make('link_telegram')->label('Telegram')->url(),
                                        TextInput::make('link_facebook')->label('Facebook')->url(),
                                        TextInput::make('link_whatsapp')->label('WhatsApp'),
                                        TextInput::make('link_instagram')->label('Instagram')->url(),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Imagens')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Banners de autenticação')
                                    ->description('Imagens usadas nas telas de login e cadastro.')
                                    ->schema([
                                        $this->imageUpload('banner_registro', 'Banner do cadastro', 'Recomendado: 700x260'),
                                        $this->imageUpload('banner_login', 'Banner do login', 'Recomendado: 700x260'),
                                    ])
                                    ->columns(2),

                                Section::make('Footer')
                                    ->description('Imagens exibidas no rodapé da plataforma.')
                                    ->schema([
                                        $this->imageUpload('footer_imagen1', 'Imagem do footer 1 500x142'),
                                        $this->imageUpload('footer_imagen2', 'Imagem do footer 2 931x170'),
                                        $this->imageUpload('footer_imagen3', 'Imagem do footer 3 174x150'),
                                        $this->imageUpload('footer_imagen4', 'Licença principal 760x80'),
                                    ])
                                    ->columns(3),

                                Section::make('Baixar App')
                                    ->description('Imagem da seção de download/aplicativo.')
                                    ->schema([
                                        $this->imageUpload('baixar_app_imagem', 'Imagem do App'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Pop-ups e destaques')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Section::make('Maiores ganhos')
                                    ->description('Controla o bloco/atalho de maiores ganhos.')
                                    ->schema([
                                        Toggle::make('maiores_ganhos_status')
                                            ->label('Ativar Maiores Ganhos')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->inline(false),
                                    ])
                                    ->columns(2),

                                Section::make('Lives ganhos')
                                    ->description('Controla a exibição de lives/ganhos ao vivo.')
                                    ->schema([
                                        Toggle::make('live_ganhos_status')
                                            ->label('Ativar Lives Ganhos')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->inline(false),
                                    ])
                                    ->columns(1),

                            ]),


                    ]),
            ]);
    }

    private function imageUpload(string $field, string $label, ?string $helper = null): FileUpload
    {
        return FileUpload::make($field)
            ->label($label)
            ->image()
            ->helperText($helper ?: 'Formatos aceitos: JPG, PNG, WEBP ou GIF até 4MB.')
            ->saveUploadedFileUsing(
                fn (TemporaryUploadedFile $file) => Core::upload($file)['path'] ?? null
            );
    }

    public function submit(): void
    {
        if (config('app.demo')) {
            Notification::make()
                ->title('Atenção')
                ->body('Você não pode realizar esta alteração na versão demo.')
                ->danger()
                ->send();

            return;
        }

        try {
            $data = $this->form->getState();
            $this->handleFileUploads($data);

            $this->custom->update($data);

            app(CacheNuker::class)->run([
                'deep' => true,
                'sessions' => false,
                'queues' => false,
            ]);

            Cache::forget('api:settings:index:v3');
            Cache::forget('api:settings:index:v4');
            Cache::forget('custom');
            Cache::forget('custom_layout');
            Cache::forever('asset_version', (string) Str::uuid());

            Notification::make()
                ->title('Configurações salvas')
                ->body('Configurações secundárias atualizadas e cache limpo.')
                ->success()
                ->send();

            $this->mount();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Erro ao salvar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function handleFileUploads(array &$data): void
    {
        foreach ($this->imageFields() as $field) {
            $data[$field] = $this->normalizeUploadedPath($data[$field] ?? null);
        }
    }

    private function imageFields(): array
    {
        return [
            'baixar_app_imagem',
            'banner_registro',
            'banner_login',
            'footer_imagen1',
            'footer_imagen2',
            'footer_imagen3',
            'footer_imagen4',
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
            $uploaded = Core::upload($value);

            return is_array($uploaded) ? ($uploaded['path'] ?? null) : null;
        }

        if (is_array($value)) {
            $first = reset($value);

            return $this->normalizeUploadedPath($first);
        }

        return null;
    }

    public function stats(): array
    {
        $custom = $this->custom ?? CustomLayout::first();

        if (! $custom) {
            return [];
        }

        return [
            'jivochat' => filled($custom->token_jivochat),
            'links' => collect([
                $custom->link_suporte,
                $custom->link_lincenca,
                $custom->link_app,
                $custom->link_telegram,
                $custom->link_facebook,
                $custom->link_whatsapp,
                $custom->link_instagram,
            ])->filter(fn ($value) => filled($value))->count(),
            'images' => collect($this->imageFields())->filter(fn ($field) => filled($custom->{$field} ?? null))->count(),
            'maiores' => (bool) $custom->maiores_ganhos_status,
            'lives' => (bool) $custom->live_ganhos_status,
            'updated_at' => $custom->updated_at,
        ];
    }

    public function previews(): array
    {
        $custom = $this->custom ?? CustomLayout::first();

        if (! $custom) {
            return [];
        }

        return [
            ['label' => 'Baixar App', 'value' => $custom->baixar_app_imagem],
            ['label' => 'Registro', 'value' => $custom->banner_registro],
            ['label' => 'Login', 'value' => $custom->banner_login],
            ['label' => 'Footer 1', 'value' => $custom->footer_imagen1],
            ['label' => 'Footer 2', 'value' => $custom->footer_imagen2],
            ['label' => 'Footer 3', 'value' => $custom->footer_imagen3],
            ['label' => 'Licença', 'value' => $custom->footer_imagen4],
        ];
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
}