<?php



namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdminPlatformSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.admin-platform-settings-page';

    protected static ?string $title = 'Configurações Primárias';
    protected static ?string $navigationLabel = 'Configurações Primárias';
    protected static ?string $navigationGroup = 'Configurações da Plataforma';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'configuracoes-primarias';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public function mount(): void
    {
        $setting = $this->record();
        $data = $setting->toArray();

        foreach (['software_favicon', 'software_logo_white', 'software_logo_black', 'pixfacil_mobile_logo', 'pixfacil_mobile_banner', 'pixfacil_loading_logo'] as $field) {
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
                Forms\Components\Tabs::make('Configurações')
                    ->persistTabInQueryString()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Logotipo e SEO')
                            ->icon('heroicon-o-computer-desktop')
                            ->schema([
                                Forms\Components\Section::make('Identidade da plataforma')
                                    ->description('Identidade oficial salva na tabela settings. A logo principal é usada no tema PixFácil mobile, login e cabeçalhos; a segunda logo é usada no loading.')
                                    ->schema([
                                        Forms\Components\TextInput::make('software_name')
                                            ->label('Nome da plataforma')
                                            ->required()
                                            ->maxLength(191)
                                            ->columnSpanFull(),

                                        Forms\Components\FileUpload::make('software_favicon')
                                            ->label('Favicon')
                                            ->image()
                                            ->helperText('Recomendado: PNG quadrado 1024x1024.')
                                            ->saveUploadedFileUsing(
                                                fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                                            ),

                                        Forms\Components\FileUpload::make('software_logo_white')
                                            ->label('Logo principal do tema PixFácil')
                                            ->image()
                                            ->helperText('Salva em settings.software_logo_white e usada no cabeçalho, login e telas mobile. Prefira PNG/WebP transparente, horizontal.')
                                            ->saveUploadedFileUsing(
                                                fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                                            ),

                                        Forms\Components\FileUpload::make('software_logo_black')
                                            ->label('Logo da tela de carregamento')
                                            ->image()
                                            ->helperText('Salva em settings.software_logo_black. Pode usar GIF animado ou imagem horizontal.')
                                            ->saveUploadedFileUsing(
                                                fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                                            ),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Tema PixFácil Mobile')
                                    ->description('Assets exclusivos do frontend mobile PixFácil V9. Estes campos ficam salvos no banco e podem ser trocados sem alterar código.')
                                    ->schema([
                                        Forms\Components\FileUpload::make('pixfacil_mobile_logo')
                                            ->label('Logo PixFácil mobile')
                                            ->image()
                                            ->helperText('PNG/WebP/SVG horizontal e transparente. Se vazio, usa a logo PixFácil incluída no tema.')
                                            ->saveUploadedFileUsing(
                                                fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                                            ),

                                        Forms\Components\FileUpload::make('pixfacil_mobile_banner')
                                            ->label('Banner principal PixFácil')
                                            ->image()
                                            ->helperText('Recomendado: aproximadamente 2,2:1. Se vazio, usa o banner premium incluído na V9.')
                                            ->saveUploadedFileUsing(
                                                fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                                            ),

                                        Forms\Components\FileUpload::make('pixfacil_loading_logo')
                                            ->label('Logo de carregamento PixFácil')
                                            ->image()
                                            ->helperText('Mantido para telas de boot futuro. A V9 não mostra loading em toda navegação.')
                                            ->saveUploadedFileUsing(
                                                fn (TemporaryUploadedFile $file) => \Helper::upload($file)['path'] ?? null
                                            ),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('SEO básico')
                                    ->description('Informações usadas por buscadores, compartilhamentos e preview social.')
                                    ->schema([
                                        Forms\Components\Textarea::make('meta_description')
                                            ->label('Meta description')
                                            ->rows(3)
                                            ->maxLength(65535),

                                        Forms\Components\TextInput::make('meta_keywords')
                                            ->label('Meta keywords')
                                            ->helperText('Separe as palavras-chave por vírgula.')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('site_url')
                                            ->label('URL base do site / canonical')
                                            ->url()
                                            ->maxLength(255),

                                        Forms\Components\Toggle::make('allow_indexing')
                                            ->label('Permitir indexação')
                                            ->helperText('Desative se não quiser indexação no Google e outros buscadores.'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Open Graph e Twitter')
                                    ->description('Textos exibidos ao compartilhar o site em WhatsApp, Facebook, X/Twitter e outros canais.')
                                    ->schema([
                                        Forms\Components\TextInput::make('og_title')
                                            ->label('OG title')
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('og_description')
                                            ->label('OG description')
                                            ->rows(3)
                                            ->maxLength(65535),

                                        Forms\Components\TextInput::make('twitter_title')
                                            ->label('Twitter title')
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('twitter_description')
                                            ->label('Twitter description')
                                            ->rows(3)
                                            ->maxLength(65535),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Roll-over')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Section::make('Proteção de bônus e depósito')
                                    ->description('Evita saque sem aposta e ajuda a proteger a operação contra abuso.')
                                    ->schema([
                                        Forms\Components\TextInput::make('rollover_deposit')
                                            ->label('Roll-over depósito')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->default(1)
                                            ->suffix('x')
                                            ->helperText('Recomendado: 2x.'),

                                        Forms\Components\TextInput::make('rollover')
                                            ->label('Roll-over bônus')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->default(1)
                                            ->suffix('x')
                                            ->helperText('Recomendado: 5x.'),

                                        Forms\Components\Toggle::make('disable_rollover')
                                            ->label('Desativar rollover')
                                            ->helperText('Quando ativo, o sistema ignora a trava de rollover.'),
                                    ])
                                    ->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Limites de saque')
                            ->icon('heroicon-o-hand-raised')
                            ->schema([
                                Forms\Components\Section::make('Limite por período')
                                    ->description('Define quanto um usuário pode sacar por período.')
                                    ->schema([
                                        Forms\Components\TextInput::make('withdrawal_limit')
                                            ->label('Limite de saque')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal'),

                                        Forms\Components\Select::make('withdrawal_period')
                                            ->label('Período')
                                            ->options([
                                                'daily' => 'Dia',
                                                'weekly' => 'Semana',
                                                'monthly' => 'Mês',
                                                'yearly' => 'Ano',
                                            ])
                                            ->native(false),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Aprovação automática')
                                    ->description('Saques até um valor definido podem ser aprovados automaticamente. O limite operacional continua sendo controlado pelo backend.')
                                    ->schema([
                                        Forms\Components\Toggle::make('withdrawal_auto_approve')
                                            ->label('Aprovar saque automaticamente'),

                                        Forms\Components\TextInput::make('withdrawal_auto_approve_max')
                                            ->label('Valor máximo de autoaprovação')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->default(0)
                                            ->helperText('Após 3 autoaprovações no dia, os próximos devem ir para aprovação manual.'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Central financeira')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                // A escolha do gateway e os toggles moravam aqui
                                // TAMBÉM, e a lista desta página só conhecia o
                                // GeraPix: quem tivesse escolhido outro via um
                                // campo em branco e, ao mexer nele, voltava para
                                // GeraPix sem querer. Agora tudo isso é da página
                                // de Gateways de Pagamento, e só dela.
                                Forms\Components\Section::make('Gateways de pagamento')
                                    ->description('A escolha do gateway de depósito e de saque, as credenciais e o liga/desliga de cada um ficam na página de Gateways de Pagamento.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('gateways_atalho')
                                            ->label('Configuração atual')
                                            ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                                '<div style="font-size:13px;margin-bottom:10px">Depósito: <strong>'
                                                . e(\App\Services\Gateways\GatewayManager::labelFor($this->record()->deposit_gateway))
                                                . '</strong> &nbsp;·&nbsp; Saque: <strong>'
                                                . e(\App\Services\Gateways\GatewayManager::labelFor($this->record()->saque))
                                                . '</strong></div>'
                                                . '<a href="' . e(AdminPaymentGatewaysPage::getUrl()) . '"'
                                                . ' style="display:inline-flex;align-items:center;padding:9px 13px;border-radius:12px;'
                                                . 'background:#00b91e;color:#fff;font-weight:800;font-size:13px;text-decoration:none">'
                                                . 'ABRIR GATEWAYS DE PAGAMENTO</a>'
                                            )),
                                    ])
                                    ->columns(1),

                                Forms\Components\Section::make('Depósitos e saques')
                                    ->description('Valores mínimos, máximos e bônus inicial.')
                                    ->schema([
                                        Forms\Components\TextInput::make('min_deposit')
                                            ->label('Depósito mínimo')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal'),

                                        Forms\Components\TextInput::make('max_deposit')
                                            ->label('Depósito máximo')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal'),

                                        Forms\Components\TextInput::make('min_withdrawal')
                                            ->label('Saque mínimo')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal'),

                                        Forms\Components\TextInput::make('max_withdrawal')
                                            ->label('Saque máximo')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal'),

                                        Forms\Components\TextInput::make('initial_bonus')
                                            ->label('Bônus inicial')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->suffix('%'),
                                    ])
                                    ->columns(5),

                                Forms\Components\Section::make('CPA / afiliados')
                                    ->description('Configuração padrão de CPA usada para afiliados.')
                                    ->schema([
                                        Forms\Components\TextInput::make('cpa_baseline')
                                            ->label('Depósito mínimo CPA')
                                            ->prefix('R$')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->helperText('Valor mínimo que o indicado deve depositar para liberar CPA.'),

                                        Forms\Components\TextInput::make('cpa_value')
                                            ->label('Percentual CPA')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->suffix('%')
                                            ->helperText('Ex.: 20 = 20% do depósito.'),

                                        Forms\Components\TextInput::make('revshare_reverse')
                                            ->label('Revshare reverso')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->suffix('%')
                                            ->helperText('Campo mantido por compatibilidade, se usado no fluxo de afiliados.'),
                                    ])
                                    ->columns(3),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        if (config('app.demo')) {
            Notification::make()
                ->title('Atenção')
                ->body('Você não pode realizar esta alteração na versão demo.')
                ->danger()
                ->send();

            return;
        }

        $setting = $this->record();
        $data = $this->form->getState();

        foreach (['software_favicon', 'software_logo_white', 'software_logo_black', 'pixfacil_mobile_logo', 'pixfacil_mobile_banner', 'pixfacil_loading_logo'] as $field) {
            $data[$field] = $this->normalizeUploadedFileForDatabase($data[$field] ?? null);
        }

        $setting->update($data);

        Cache::put('setting', $setting->fresh());
        Cache::forget('api:settings:index:v3');
        Cache::forget('api:settings:index:v4');
        Cache::forget('custom');
        Cache::forget('custom_layout');
        Cache::put('asset_version', 'v' . now()->timestamp);

        Notification::make()
            ->title('Configurações salvas')
            ->body('As configurações da plataforma foram atualizadas.')
            ->success()
            ->send();

        $this->mount();
    }

    public function record(): Setting
    {
        return Setting::query()->firstOrCreate(['id' => 1], [
            'software_name' => config('app.name', 'Plataforma'),
            'min_deposit' => 0,
            'max_deposit' => 0,
            'min_withdrawal' => 0,
            'max_withdrawal' => 0,
            'rollover' => 1,
            'rollover_deposit' => 1,
            'disable_rollover' => false,
            'withdrawal_period' => 'daily',
            'withdrawal_limit' => 0,
            'withdrawal_auto_approve' => false,
            'withdrawal_auto_approve_max' => 0,
            'allow_indexing' => false,
        ]);
    }

    public function stats(): array
    {
        $setting = $this->record();

        return [
            'name' => $setting->software_name ?: '-',
            // labelFor em vez da chave crua: mostra "GeraPix", não "gerapix". Se
            // estiver vazio, mostra o fallback — que é quem realmente processa.
            'deposit_gateway' => \App\Services\Gateways\GatewayManager::labelFor($setting->deposit_gateway),
            'withdraw_gateway' => \App\Services\Gateways\GatewayManager::labelFor($setting->saque),
            'min_deposit' => $this->money($setting->min_deposit),
            'min_withdrawal' => $this->money($setting->min_withdrawal),
            'rollover' => (float) $setting->rollover,
            'rollover_deposit' => (float) $setting->rollover_deposit,
            'rollover_disabled' => (bool) $setting->disable_rollover,
            'auto_approve' => (bool) $setting->withdrawal_auto_approve,
            'auto_approve_max' => $this->money($setting->withdrawal_auto_approve_max),
            'indexing' => (bool) $setting->allow_indexing,
            'updated_at' => $setting->updated_at,
            'favicon' => $this->imageUrl($setting->software_favicon),
            'logo_white' => $this->imageUrl($setting->software_logo_white),
            'logo_black' => $this->imageUrl($setting->software_logo_black),
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

    private function normalizeUploadedFileForDatabase(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_string($value)) {
            return ltrim($value, '/');
        }

        if (! is_array($value)) {
            return null;
        }

        $first = reset($value);

        if ($first instanceof TemporaryUploadedFile) {
            $path = \Helper::upload($first);

            return $path['path'] ?? null;
        }

        if (is_string($first)) {
            return ltrim($first, '/');
        }

        return null;
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

    public function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}